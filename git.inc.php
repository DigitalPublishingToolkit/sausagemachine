<?php

require_once('config.inc.php');
require_once('util.inc.php');

/**
 *	Return the cache path for a cache key
 *	@param $key cache key
 *	@return string, without tailing slash
 */
function cache_dir($key) {
	return rtrim(config('content_dir', 'content'), '/') . '/cache/' . $key;
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
 *	Reset a repository to its original state
 *
 *	This also removes uncommitted files.
 *	@param $tmp_key tmp key
 *	@return true if successful, false if not
 */
function cleanup_repo($tmp_key) {
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
 *	Get a remote Git repo and return the tmp key it can accessed with
 *
 *	Call release_repo() with the tmp key, after it is no longer needed.
 *	@param $url Git (clone) URL
 *	@param $branch branch to check out (default is "master")
 *	@return tmp key, or false if unsuccessful
 */
function get_repo($url, $branch = 'master') {
	$cache_key = git_url_to_cache_key($url);
	if ($cache_key === false) {
		return false;
	}
	$tmp_key = tmp_key();

	// preserve current working directory
	$old_cwd = getcwd();
	// make sure the content directory is set up
	if (false === check_content_dir()) {
		return false;
	}
	// create directories and files as permissive as possible
	$old_umask = @umask(0000);

	// serve from cache, if possible, or clone from remote
	if (is_dir(cache_dir($cache_key))) {
		@chdir(cache_dir($cache_key));
		// XXX: lock
		// make sure the repo is up to date
		// XXX: this could be rate-limited, only every n minutes
		@exec('git fetch 2>&1', $out, $ret_val);
		if ($ret_val !== 0) {
			// fetching failed
			@chdir($old_cwd);
			@umask($old_umask);
			return false;
		}
		// update file modification time for LRU
		@touch(cache_dir($cache_key));
		// checkout the requested branch
		@exec('git checkout -f -B ' . escapeshellarg($branch) . ' ' . escapeshellarg('origin/' . $branch) . ' 2>&1', $out, $ret_val);
		if ($ret_val !== 0) {
			// checking out failed
			@chdir($old_cwd);
			@umask($old_umask);
			var_dump($out);
			return false;
		}
		@chdir($old_cwd);

		// copy to tmp
		if (false === cp_recursive(cache_dir($cache_key), tmp_dir($tmp_key))) {
			// copying failed, remove again
			rm_recursive(tmp_dir($tmp_key));
			@umask($old_umask);
			return false;
		}
		// after copying the files from the cache some might end up with the wrong permissions
		// due to PHP's umask etc, so clean up the repository one more time, to ensure we have
		// a clean working copy
		if (false === cleanup_repo($tmp_key)) {
			// cleaning failed, remove repo in tmp
			rm_recursive(tmp_dir($tmp_key));
			@umask($old_umask);
			return false;
		}
	} else {
		// clone repo in tmp
		if (false === @mkdir(tmp_dir($tmp_key))) {
			@umask($old_umask);
			return false;
		}
		@chdir(tmp_dir($tmp_key));
		@exec('git clone -b ' . escapeshellarg($branch) . ' ' . escapeshellarg($url) . ' . 2>&1', $out, $ret_val);
		@chdir($old_cwd);
		if ($ret_val !== 0) {
			// cloning failed, remove repo in tmp
			rm_recursive(tmp_dir($tmp_key));
			@umask($old_umask);
			return false;
		}
	}

	// back to original umask
	@umask($old_umask);

	return $tmp_key;
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
 *	Release a Git repository that was referenced by get_repo()
 *
 *	@param $tmp_key tmp key
 *	@return true if sucessful, false if not
 */
function release_repo($tmp_key) {
	if (!is_dir(tmp_dir($tmp_key))) {
		return false;
	}

	// get the clone url
	$old_cwd = getcwd();
	@chdir(tmp_dir($tmp_key));
	@exec('git config --get remote.origin.url 2>&1', $out, $ret_val);
	@chdir($old_cwd);
	if ($ret_val !== 0) {
		// error getting the url
		rm_recursive(tmp_dir($tmp_key));
		return false;
	}

	// check if repo is already in cache
	$cache_key = git_url_to_cache_key($out[0]);
	if (is_dir(cache_dir($cache_key))) {
		// already cached
		rm_recursive(tmp_dir($tmp_key));
	} else {
		// not yet cached, clean up
		if (false === cleanup_repo($tmp_key)) {
			// error cleaning up
			rm_recursive(tmp_dir($tmp_key));
			return false;
		}
		// and move to the cache directory
		if (false === @rename(tmp_dir($tmp_key), cache_dir($cache_key))) {
			// error moving to cache directory
			rm_recursive(tmp_dir($tmp_key));
			return false;
		}
	}

	return true;
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
 *	Return the tmp path for a tmp key
 *	@param $key tmp key
 *	@return string, without trailing slash
 */
function tmp_dir($key) {
	return rtrim(config('content_dir', 'content'), '/') . '/tmp/' . $key;
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
