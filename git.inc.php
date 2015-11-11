<?php

@require_once('config.inc.php');
require_once('util.inc.php');

// XXX: locking
// XXX: invalidate cache after push?

/**
 *	Return the path for a cache key
 *	@param $cache_key cache key
 *	@return string, without tailing slash
 */
function cache_dir($cache_key) {
	return rtrim(config('content_dir', 'content'), '/') . '/cache/' . $cache_key;
}

/**
 *	Delete repositories in the cache that haven't been used recently
 *	@return true if successful, false if not
 */
function check_cache_lru() {
	// XXX: implement
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
 *	@return true if there have been leftover copies, false if not
 */
function check_tmp_dir_age() {
	// XXX: implement
}

/**
 *	Get a private copy of a remote Git repository
 *
 *	The private copy can be manipulated with the returned tmp key. Call
 *	release_repo() after it is no longer needed.
 *	@param $url Git (clone) URL
 *	@param $branch branch to check out (default is "master")
 *	@return tmp key, or false if unsuccessful
 */
function get_repo($url, $branch = 'master') {
	$tmp_key = tmp_key();

	// get a cached copy, currently on the master branch
	$cache_key = get_repo_in_cache($url);
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
	} else {
		return true;
	}
}

/**
 *	Get a remote Git repo for reading (only)
 *
 *	This will always return the default (master) branch. Don't call release_repo()
 *	together with this function.
 *	@param $url Git (clone) URL
 *	@return cache key, or false if unsuccessful
 */
function get_repo_in_cache($url) {
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
		if (false === repo_check_for_update($cache_key)) {
			return false;
		}
	} else {
		if (false === repo_add_to_cache($url)) {
			return false;
		}
	}

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

	@chdir(cache_key($cache_key));
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
 *	Fetch the remote repository if this hasn't been done recently
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
	@exec('git fetch 2>&1', $out, $ret_val);

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
