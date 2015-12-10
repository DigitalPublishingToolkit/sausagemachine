<?php

/*
	Git manipulation and caching for Sausage Machine
	Copyright (C) 2015  Gottfried Haider for PublishingLab

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

@require_once('config.inc.php');
require_once('util.inc.php');


/**
 *	Return the path for a cache key
 *	@param $cache_key cache key
 *	@return string, without trailing slash
 */
function cache_dir($cache_key) {
	return rtrim(config('content_dir', 'content'), '/') . '/cache/' . $cache_key;
}


/**
 *	Delete repositories in the cache that haven't been used recently
 *	@return true if successful, false if not
 */
function check_cache_lru() {
	$max_cached = config('repo_max_cached', 0);
	if ($max_cached == 0) {
		// check disabled
		return true;
	}

	$fns = @scandir(cache_dir());
	if ($fns === false) {
		// cache_dir might not yet exist
		return true;
	}

	$success = true;

	$cached = array();
	foreach ($fns as $fn) {
		if (in_array($fn, array('.', '..'))) {
			continue;
		}
		if (!@is_dir(cache_dir($fn))) {
			continue;
		}
		$mtime = @filemtime(cache_dir($fn));
		if ($mtime === false) {
			$success = false;
		}
		$cached[$fn] = $mtime;
	}
	// newer repos come first
	arsort($cached);

	// delete excess cached repositories
	for ($i=$max_cached; $i < count($cached); $i++) {
		$cache_key = array_keys($cached)[$i];
		if (false === rm_recursive(cache_dir($cache_key))) {
			$success = false;
		}
	}

	return $success;
}


/**
 *	Make sure the content directory contains a cache and tmp subdirectory
 *	@return true if successful, false if not
 */
function check_content_dir() {
	$content_dir = rtrim(config('content_dir', 'content'), '/');
	if (!is_dir($content_dir . '/cache')) {
		$old_umask = @umask(0000);
		$ret = @mkdir($content_dir . '/cache');
		@umask($old_umask);
		if ($ret === false) {
			return false;
		}
	}
	if (!is_dir($content_dir . '/tmp')) {
		$old_umask = @umask(0000);
		$ret = @mkdir($content_dir . '/tmp');
		@umask($old_umask);
		if ($ret === false) {
			return false;
		}
	}
	return true;
}


/**
 *	Delete leftover copies of repositories in the tmp directory
 *	@return true if successful, false if not
 */
function check_tmp_dir_age() {
	$max_age = config('temp_max_age', 0);
	if ($max_age == 0) {
		// check disabled
		return true;
	}

	$fns = @scandir(tmp_dir());
	if ($fns === false) {
		// cache_dir might not yet exist
		return true;
	}

	$success = true;

	foreach ($fns as $fn) {
		if (in_array($fn, array('.', '..'))) {
			continue;
		}
		if (!@is_dir(tmp_dir($fn))) {
			continue;
		}
		$mtime = @filemtime(tmp_dir($fn));
		if ($mtime === false) {
			$success = false;
			continue;
		}
		// delete old temporary repositories
		if ($max_age < time()-$mtime) {
			if (false === rm_recursive(tmp_dir($fn))) {
				$success = false;
			}
		}
	}

	return $success;
}


/**
 *	Get a private copy of a remote Git repository
 *
 *	The private copy can be manipulated with the returned tmp key. Call
 *	release_repo() after it is no longer needed.
 *	@param $url Git (clone) URL
 *	@param $branch branch to check out (default is "master")
  *	@param $force_update force a fetch
 *	@return tmp key, or false if unsuccessful
 */
function get_repo($url, $branch = 'master', $force_update = false) {
	$tmp_key = tmp_key();

	// get a cached copy, currently on the master branch
	$cache_key = get_repo_for_reading($url, $force_update);
	if ($cache_key === false) {
		return false;
	}

	// create files and directories as permissive as possible
	$old_umask = @umask(0000);

	// copy to tmp
	if (false === cp_recursive(cache_dir($cache_key), tmp_dir($tmp_key))) {
		// copying failed, remove again
		rm_recursive(tmp_dir($tmp_key));
		@umask($old_umask);
		return false;
	}

	if ($branch === 'master') {
		// XXX: test & document why
		$ret = repo_cleanup($tmp_key);
	} else {
		$ret = repo_checkout_branch($tmp_key, $branch);
		// XXX: also needs repo_cleanup()?
	}

	@umask($old_umask);

	if ($ret === false) {
		// copying failed, remove again
		rm_recursive(tmp_dir($tmp_key));
		return false;
	}

	// XXX: this could be done asynchronously
	check_tmp_dir_age();

	return $tmp_key;
}


/**
 *	Get a remote Git repo for reading only
 *
 *	This will always return the default (master) branch. Don't call release_repo()
 *	together with this function.
 *	@param $url Git (clone) URL
 *	@param $force_update force a fetch
 *	@return cache key, or false if unsuccessful
 */
function get_repo_for_reading($url, $force_update = false) {
	$cache_key = git_url_to_cache_key($url);
	if ($cache_key === false) {
		return false;
	}

	// make sure the content directory is set up
	if (false === check_content_dir()) {
		return false;
	}

	// serve from cache, if possible, or clone from remote
	if (is_dir(cache_dir($cache_key))) {
		if (false === repo_check_for_update($cache_key, $force_update)) {
			return false;
		}
	} else {
		if (false === repo_add_to_cache($url)) {
			return false;
		}
	}

	// XXX: this could be done asynchronously
	check_cache_lru();

	return $cache_key;
}


/**
 *	Convert a Git URL to a key to lookup the local cache
 *	@param $url Git (clone) URL
 *	@return string
 */
function git_url_to_cache_key($url) {
	$pos = strpos($url, '://');
	if ($pos === false) {
		return false;
	}
	// strip protocol that might be variable
	$key = substr($url, $pos+3);
	// replace slashes
	$key = str_replace('/', '-', $key);
	// replace .git suffix that is optional with many services
	if (substr($key, -4) == '.git') {
		$key = substr($key, 0, -4);
	}
	return $key;
}


/**
 *	Release a repository after it is no longer being used
 *
 *	Use this together with get_repo().
 *	@param $tmp_key tmp key
 *	@return true if successful, false if not
 */
function release_repo($tmp_key) {
	return rm_recursive(tmp_dir($tmp_key));
}


/**
 *	Helper function to add a remote repository to the cache
 *
 *	Called by get_repo_in_cache(). Assumes that the repository isn't yet cached.
 *	@param $url Git (clone) URL
 *	@return true if successful, false if not
 */
function repo_add_to_cache($url) {
	$cache_key = git_url_to_cache_key($url);
	if ($cache_key === false) {
		return false;
	}

	$old_cwd = getcwd();
	$old_umask = @umask(0000);

	// create directory in cache
	if (false === @mkdir(cache_dir($cache_key))) {
		@umask($old_umask);
		return false;
	}

	@chdir(cache_dir($cache_key));
	// all repos in cache are on the master branch
	@exec('git clone -b master ' . escapeshellarg($url) . ' . 2>&1', $out, $ret_val);

	@umask($old_umask);
	@chdir($old_cwd);

	if ($ret_val !== 0) {
		// cloning failed, remote directory in cache
		rm_recursive(tmp_dir($cache_key));
		return false;
	} else {
		return true;
	}
}


/**
 *	Fetch the remote repository, if this hasn't been done recently, and checkout the remote master branch
 *	@param $cache_key cache key
 *	@param $force force a fetch
 *	@return true if succesful, false if not
 */
function repo_check_for_update($cache_key, $force = false) {
	$mtime = @filemtime(cache_dir($cache_dir));
	if ($mtime === false) {
		return false;
	}

	if (!$force && time()-$mtime < config('repo_cache_time')) {
		// current version is recent enough
		return true;
	}

	$old_cwd = getcwd();
	$old_umask = @umask(0000);

	// update file modification time for LRU
	@touch(cache_dir($cache_key));

	@chdir(cache_dir($cache_key));
	@exec('git fetch --all 2>&1', $out, $ret_val);
	if ($ret_val === 0) {
		@exec('git reset --hard origin/master 2>&1', $out, $ret_val);
	}

	@umask($old_umask);
	@chdir($old_cwd);

	return ($ret_val === 0);
}


/**
 *	Switch the branch of a repository
 *	@param $tmp_key tmp key
 *	@param $branch branch to check out
 *	@return true if sucessful, false if not
 */
function repo_checkout_branch($tmp_key, $branch = 'master') {
	$old_cwd = getcwd();
	$old_umask = @umask(0000);

	@chdir(tmp_dir($tmp_key));
	@exec('git checkout -f -B ' . escapeshellarg($branch) . ' ' . escapeshellarg('origin/' . $branch) . ' 2>&1', $out, $ret_val);

	@umask($old_umask);
	@chdir($old_cwd);

	return ($ret_val === 0);
}


/**
 *	Reset a repository to its original state
 *
 *	This also removes uncommitted files.
 *	@param $tmp_key tmp key
 *	@return true if successful, false if not
 */
function repo_cleanup($tmp_key) {
	$old_cwd = getcwd();
	@chdir(tmp_dir($tmp_key));
	@exec('git reset --hard 2>&1', $out, $ret_val);
	if ($ret_val !== 0) {
		@chdir($old_cwd);
		return false;
	}
	@exec('git clean -f -x 2>&1', $out, $ret_val);
	@chdir($old_cwd);
	if ($ret_val !== 0) {
		return false;
	} else {
		return true;
	}
}


/**
 *	Commit changes to a Git repository
 *	@param $tmp_key tmp key
 *	@param $msg commit message
 *	@param $author commit author
 *	@return true if successful, false if not
 */
function repo_commit($tmp_key, $msg, $author = 'Git User <username@example.edu>') {
	$old_cwd = getcwd();
	@chdir(tmp_dir($tmp_key));
	@exec('git commit -a --author=' . escapeshellarg($author) . ' -m ' . escapeshellarg($msg) . ' 2>&1', $out, $ret_val);
	@chdir($old_cwd);
	if ($ret_val !== 0) {
		return false;
	} else {
		return true;
	}
}


function repo_get_all_files($tmp_key) {
	$fns = list_files_recursive(tmp_dir($tmp_key));
	if ($fns === false) {
		return false;
	}
	$ret = array();
	foreach ($fns as $fn) {
		// filter the actual git repository
		if (strpos($fn, '.git/') !== false) {
			continue;
		}
		// and .gitignore files
		if (strpos($fn, '.gitignore') !== false) {
			continue;
		}
		$ret[] = $fn;
	}
	return $ret;
}


/**
 *	Return all modified files in the working directory of a Git repository
 *	@param $tmp_key tmp key
 *	@return array of filenames, or false if unsuccessful
 */
function repo_get_modified_files($tmp_key) {
	$old_cwd = getcwd();
	@chdir(tmp_dir($tmp_key));
	// outputs "modified" (seems to include: deleted) and "other" (e.g. untracked) files
	@exec('git ls-files -m -o 2>&1', $out, $ret_val);
	@chdir($old_cwd);
	if ($ret_val !== 0) {
		return false;
	} else {
		return $out;
	}
}


/**
 *	Get remote URL of a Git repository
 *	@param $tmp_key tmp key
 *	@return String, or false if unsuccessful
 */
function repo_get_url($tmp_key) {
	$old_cwd = getcwd();
	@chdir(tmp_dir($tmp_key));
	@exec('git config --get remote.origin.url 2>&1', $out, $ret_val);
	@chdir($old_cwd);
	if ($ret_val !== 0) {
		return false;
	} else {
		return $out[0];
	}
}


/**
 *	Return whether the working directory of a Git repository has modified files
 *	@param $tmp_key tmp key
 *	@return bool
 */
function repo_has_modified_files($tmp_key) {
	$modified = repo_get_modified_files($tmp_key);
	if ($modified === false) {
		return false;
	} else {
		return (0 < count($modified));
	}
}


/**
 *	Push all branches of a Git repository to a remote URL
 *	@param $tmp_key tmp key
 *	@param $url Git Push URL (e.g. ssh://git@github.com/...)
 *	@return true if successful, false if not
 */
function repo_push($tmp_key, $url) {
	$old_cwd = getcwd();
	@chdir(tmp_dir($tmp_key));
	@exec('git push --all ' . escapeshellarg($url). ' 2>&1', $out, $ret_val);
	@chdir($old_cwd);
	if ($ret_val === 1) {
		// not fast-forward, tell caller to fetch & try again
		return NULL;
	} else if ($ret_val !== 0) {
		return false;
	} else {
		return true;
	}
}


/**
 *	Reset the index of a Git repository to some earlier state
 *	@param $tmp_key tmp key
 *	@param $offset 1 rewinds to the second most recent commit
 *	@return true if successful, false if not
 */
function repo_rewind($tmp_key, $offset = 1) {
	$old_cwd = getcwd();
	@chdir(tmp_dir($tmp_key));
	@exec('git reset --hard HEAD~' . intval($offset) . ' 2>&1', $out, $ret_val);
	@chdir($old_cwd);
	if ($ret_val !== 0) {
		return false;
	} else {
		return true;
	}
}


/**
 *	Add files to be committed
 *	@param $tmp_key tmp key
 *	@param $files array of filenames
 *	@return true if successful, false if not
 */
function repo_stage_files($tmp_key, $files = array()) {
	$old_cwd = getcwd();
	@chdir(tmp_dir($tmp_key));
	$escaped_files = '';
	foreach ($files as $file) {
		if (!empty($escaped_files)) {
			$escaped_files .= ' ';
		}
		$escaped_files .= escapeshellarg($file);
	}
	@exec('git add -f --ignore-errors ' . $escaped_files . ' 2>&1', $out, $ret_val);
	@chdir($old_cwd);
	if ($ret_val !== 0) {
		return false;
	} else {
		return true;
	}
}


// XXX: add to API
function repo_touch($tmp_key) {
	// update file modification time
	@touch(tmp_dir($tmp_key);
}


/**
 *	Return the path for a tmp key
 *	@param $tmp_key tmp key
 *	@return string, without trailing slash
 */
function tmp_dir($tmp_key) {
	return rtrim(config('content_dir', 'content'), '/') . '/tmp/' . $tmp_key;
}


/**
 *	Return a tmp key based on the current request's timestamp
 *	@return string tmp key
 */
function tmp_key() {
	if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
		// seems to be at least four decimal places
		return strval(floor($_SERVER['REQUEST_TIME_FLOAT']*10000));
	} else {
		return strval(floor(microtime(true)*10000));
	}
}
