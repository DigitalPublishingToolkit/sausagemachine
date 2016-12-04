<?php

/*
	RESTful API for Sausage Machine
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

session_start();
if( !isset($_SESSION['repos_cleaned']) ) {
    $_SESSION['repos_cleaned']=false;
}

@require_once('config.inc.php');
require_once('git.inc.php');
require_once('hybrid.inc.php');
require_once('makefile.inc.php');
require_once('router.inc.php');
require_once('util.inc.php');

function api_clean_repos($repos){
    $len = count($repos);
	for ($i=$len; $i>=1; $i--){
		$repo = $repos[$i-1];
		if(checkUrl(preg_replace('/\\.git$/', '', $repo['repo'])) == false) {
			array_splice($repos, $i-1, 1);
		} else {
			if ($repo['repo'] === config('default_repo')) {
				$repo['default'] = true;
			} else {
				$repo['default'] = false;
			}
		}
	}
	$_SESSION['repos_cleaned']=true;
	$_SESSION['repos']=$repos;
    return $repos;
}

/**
 *	Get a list of (template) repositories
 */
function api_get_repos($param = array()) {
    $repos = config('repos', array());
    // Clean repos only once per session
    if( isset($_SESSION['repos_cleaned']) && isset($_SESSION['repos']) ) {
        return $_SESSION['repos'];
    }
    return api_clean_repos($repos);
}

/**
 *	Get a list of all files in a (template) repository
 *	@param $param[1] (template) repository
 */
function api_get_repo_files($param = array()) {
	$repo = $param[1];

	$cached = get_repo_for_reading($repo);
	if ($cached === false) {
		router_error_404('Cannot get ' . $repo);
	}

	$files = repo_get_all_files(cache_dir($cached));
	if ($files === false) {
		router_error_500('Cannot get files of ' . $repo);
	}

	return array('files' => $files);
}


/**
 *	Get a file from a (template) repository
 *	@param $param[1] (template) repository
 *	@param $param['fn'] filename
 */
function api_get_repo_files_raw($param = array()) {
	$repo = $param[1];

	$cached = get_repo_for_reading($repo);
	if ($cached === false) {
		router_error_404('Cannot get ' . $repo);
	}

	if (empty($param['fn'])) {
		router_error_400('Required parameter fn missing or empty');
	} else {
		$fn = $param['fn'];
	}

	if (strpos($fn, '../') !== false) {
		// thwart possible attempts to get to files outside of the content directory
		router_error_400('Illegal filename ' . $fn . ' for ' . $repo);
	} else {
		$path = cache_dir($cached) . '/' . $fn;
	}

	if (!@is_file($path)) {
		router_error_400('Cannot get file ' . $fn . ' in ' . $repo);
	}

	// serve with proper MIME type
	@header('Content-Type: ' . get_mime($path));
	@header('Content-Length: ' . @filesize($path));
	@readfile($path);
	die();
}


/**
 *	Get a list of Makefile targets for a (template) repository
 *	@param $param[1] (template) repository
 */
function api_get_repo_targets($param = array()) {
	$repo = $param[1];

	$cached = get_repo_for_reading($repo);
	if ($cached === false) {
		router_error_404('Cannot get ' . $repo);
	}

	$targets = make_get_targets(cache_dir($cached));
	if ($targets === false) {
		router_error_500('Cannot get Makefile targets for ' . $repo);
	}

	$ignore_targets = config('ignore_targets', array());
	$target_descriptions = config('target_descriptions', array());
	$default_target = config('default_target');

	$ret = array();
	foreach ($targets as $target) {
		// filter certain targets according to ignore_targets config
		if (in_array($target, $ignore_targets)) {
			continue;
		}

		$tmp = array('target' => $target);
		$tmp['description'] = isset($target_descriptions[$target]) ? $target_descriptions[$target] : $target;
		$tmp['default'] = ($target === $default_target);
		$ret[] = $tmp;
	}
	return $ret;
}



/**
 *	Get a list of temporary (working) repositories
 */
function api_get_temps($param = array()) {
	$fns = @scandir(tmp_dir());
	if ($fns === false) {
		// tmp_dir might not yet exist
		return array();
	}

	$ret = array();
	foreach ($fns as $fn) {
		if (in_array($fn, array('.', '..'))) {
			continue;
		} else if (!@is_dir(tmp_dir() . '/' . $fn)) {
			// not a repository
			continue;
		}
		$ret[] = $fn;
	}
	return $ret;
}


/**
 *	Create a new temporary (working) repository
 *	@param $param['repo'] base repository (default: default_repo config)
 */
function api_post_temp_create($param = array()) {
	if (isset($param['repo'])) {
		$repo = $param['repo'];
	} else {
		// if no repo is given, initialize from the default
		$repo = config('default_repo');
	}

	$temp = get_repo($repo);
	if ($temp === false) {
		router_error_500('Cannot get a copy of ' . $repo . ' for writing');
	}

	return array(
		'temp' => $temp,
		'repo' => repo_get_url($temp),
		'branch' => 'master',
		'commit' => repo_get_current_commit(tmp_dir($temp)),
		'files' => repo_get_all_files(tmp_dir($temp))
	);
}


/**
 *	Get information about a temporary (working) repository
 *	@param $param[1] temporary repository
 */
function api_get_temp($param = array()) {
	$temp = $param[1];

	if (!@is_dir(tmp_dir($temp))) {
		router_error_404('Cannot get ' . $temp);
	}

	return array(
		'temp' => $temp,
		'repo' => repo_get_url($temp),
		'branch' => 'master',
		'commit' => repo_get_current_commit(tmp_dir($temp)),
		'files' => repo_get_all_files(tmp_dir($temp)),
		'modified' => repo_get_modified_files($temp)
	);
}


/**
 *	Get a file from a temporary (working) repository
 *	@param $param[1] temporary repository
 *	@param $param[2] filename
 *	@param $param['format'] "download", "json" or "raw" (default)
 */
function api_get_temp_file($param = array()) {
	$temp = $param[1];
	$fn = $param[2];

	if (strpos($fn, '../') !== false) {
		// thwart possible attempts to get to files outside of the content directory
		router_error_400('Illegal filename ' . $fn . ' for ' . $temp);
	} else {
		$path = tmp_dir($temp) . '/' . $fn;
	}

	if (!@is_file($path)) {
		router_error_404('Cannot get file ' . $fn . ' in ' . $temp);
	}

	if (isset($param['format'])) {
		$format = $param['format'];
	} else {
		$format = 'raw';
	}

	if ($format === 'download') {
		// force browser to download
		@header('Content-Type: application/octet-stream');
		@header('Content-Disposition: attachment; filename="' . basename($fn) . '"');
		@header('Content-Length: ' . @filesize($path));
		@readfile($path);
		die();
	} else if ($format === 'json') {
		// serve base64-encoded
		return array(
			'fn' => $fn,
			'mime' => get_mime($path),
			'data' => base64_encode(@file_get_contents($path))
		);
	} else if ($format === 'raw') {
		// serve with proper MIME type
		$mime = get_mime($path);
		@header('Content-Type: ' . $mime);
		@header('Content-Length: ' . @filesize($path));

		// HACK: add a <base> element to the <head> of any HTML page passing through
		// XXX (later): make this configurable via a $param
		if ($mime !== 'text/html') {
			@readfile($path);
		} else {
			$html = @file_get_contents($path);
			// XXX (later): use mod_rewrite instead of linking to the content dir directly (api.php?... does not work)
			$base = base_url() . tmp_dir($temp) . '/';
			$html = str_replace('<head>', '<head>' . "\n" . '<base href="' . $base . '"></base>', $html);
			echo $html;
		}

		die();
	} else {
		router_error_400('Unsupported format ' . $format);
	}
}


/**
 *	Update or create files in a temporary (working) repository
 *	@param $param[1] temporary repository
 *	@param $param['files'] array of { fn: 'bar', data: 'base64-encoded' }
 */
function api_post_temp_files_update($param = array()) {
	$temp = $param[1];
	if (!@is_dir(tmp_dir($temp))) {
		router_error_404('Cannot get ' . $temp);
	}

	if (@is_array($param['files'])) {
		$files = $param['files'];
	} else {
		router_error_400('Required parameter files missing or invalid');
	}

	$modified = array();
	$old_umask = @umask(0000);
	foreach ($files as $file) {
		if (!@is_string($file['fn']) || !@is_string($file['data'])) {
			// required field missing
			continue;
		}
		if (strpos($file['fn'], '../') !== false) {
			// thwart possible attempts to get to files outside of the content directory
			continue;
		}

		// recursively create directories if needed
		create_containing_dir(tmp_dir($temp) . '/' . $file['fn']);

		// save base64-encoded string as file
		if (false !== @file_put_contents(tmp_dir($temp) . '/' . $file['fn'], @base64_decode($file['data']))) {
			$modified[] = $file['fn'];
		}
	}
	@umask($old_umask);

	// reset timer
	repo_touch($temp);

	return array('modified' => $modified);
}


/**
 *	Upload files to temporary (working) repository
 *	@param $param[1] temporary repository
 *	@param $_FILES uploaded files
 *	@param $param['auto_convert'] convert certain file types (e.g. docx) instantaneously (default: true)
 */
function api_post_temp_files_upload($param = array()) {
	$temp = $param[1];
	if (!@is_dir(tmp_dir($temp))) {
		router_error_404('Cannot get ' . $temp);
	}

	if (empty($_FILES)) {
		router_error_400('No files uploaded');
	}

	if (isset($param['auto_convert'])) {
		$auto_convert = (bool)$param['auto_convert'];
	} else {
		$auto_convert = true;
	}

	$modified = array();
	$old_umask = @umask(0000);
	foreach ($_FILES as $file) {
		if (strpos($file['name'], '../') !== false) {
			// thwart possible attempts to get to files outside of the content directory
			continue;
		}

		// this uses a heuristic to place different file types
		// in the right places
		$ret = inject_uploaded_file($temp, $file, $auto_convert);
		if (is_array($ret)) {
			$modified = array_merge($modified, $ret);
		}
	}
	@umask($old_umask);
	return array('modified' => $modified);
}


/**
 *	Delete a file in a temporary (working) repository
 *	@param $param[1] temporary repository
 *	@param $param[2] filename
 */
function api_post_temp_files_delete($param = array()) {
	$temp = $param[1];
	$fn = $param[2];

	if (!@is_dir(tmp_dir($temp))) {
		router_error_404('Cannot get ' . $temp);
	}

	if (strpos($fn, '../') !== false) {
		// thwart possible attempts to get to files outside of the content directory
		router_error_400('Illegal filename ' . $fn . ' for ' . $temp);
	} else {
		$path = tmp_dir($temp) . '/' . $fn;
	}

	if (!@is_file($path)) {
		router_error_404('Cannot get file ' . $fn . ' in ' . $temp);
	}

	if (false === @unlink($path)) {
		router_error_500('Cannot delete file ' . $fn . ' in ' . $temp);
	} else {
		return true;
	}
}


/**
 *	Run the Makefile in a temporary (working) repository
 *	@param $param[1] temporary repository
 *	@param $param[2] Makefile target (default: default_target configuration option)
 *	@param $param['clean_before'] run "make clean" before the actual target (default: true)
 */
function api_post_temp_make($param = array()) {
	$temp = $param[1];
	if (empty($param[2])) {
		$target = config('default_target');
	} else {
		$target = $param[2];
	}

	if (!@is_dir(tmp_dir($temp))) {
		router_error_404('Cannot get ' . $temp);
	}

	// client can specify whether to run "make clean" before or after the actual target
	if (isset($param['clean_before'])) {
		$clean_before = (bool)$param['clean_before'];
	} else {
		$clean_before = false;
	}
	if (isset($param['clean_after'])) {
		$clean_after = (bool)$param['clean_after'];
	} else {
		$clean_after = false;
	}

	if ($clean_before) {
		make_run(tmp_dir($temp), 'clean');
	}
	// run the actual Makefile
	$start = time();
	$ret_val = make_run(tmp_dir($temp), $target, $out);
	if ($clean_after) {
		make_run(tmp_dir($temp), 'clean');
	}
	$modified = repo_get_modified_files_after($temp, $start-1);

	// reset timer
	repo_touch($temp);

	return array(
		'target' => $target,
		'modified' => $modified,
		'error' => ($ret_val === 0) ? false : $ret_val,
		'out' => $out
	);
}


/**
 *	Commit files to a temporary (working) repository
 *	@param $param[1] temporary repository
 *	@param $param['files'] array of filenames to add (default: all modified files)
 *	@param $param['clean_before'] run "make clean" before determining the modified files to add
 *	@param $param['message'] commit message
 *	@param $param['author'] author to commit as
 */
function api_post_temp_commit($param = array()) {
	$temp = $param[1];
	if (!@is_dir(tmp_dir($temp))) {
		router_error_404('Cannot get ' . $temp);
	}

	if (isset($param['clean_before'])) {
		$clean_before = (bool)$param['clean_before'];
	} else {
		$clean_before = true;
	}

	if ($clean_before) {
		make_run(tmp_dir($temp), 'clean');
	}

	if (@is_array($param['files'])) {
		$files = $param['files'];
	} else {
		// commit all modified files by default
		$files = repo_get_modified_files($temp);
	}
	if (@is_string($param['message'])) {
		$message = $param['message'];
	} else {
		$message = 'Add initial files';
	}
	// XXX (later): add author parameter

	// add files to repository
	if (false === repo_stage_files($temp, $files)) {
		router_error_500('Cannot add ' . implode(', ', $files) . ' to ' . $temp);
	}

	// commit
	if (false === repo_commit($temp, $message)) {
		router_error_500('Cannot commit to ' . $temp);
	}

	return array('commit' => repo_get_current_commit(tmp_dir($temp)));
}


/**
 *	Push a temporary (working) repository to a remote URL
 *	@param $param[1] temporary (working) repository
 *	@param $param['repo'] rempote URL
 */
function api_post_temp_push($param = array()) {
	$temp = $param[1];
	if (!@is_dir(tmp_dir($temp))) {
		router_error_404('Cannot get ' . $temp);
	}

	if (empty($param['repo'])) {
		router_error_400('Required parameter repo missing or empty');
	}

	return repo_push($temp, $param['repo']);
}


/**
 *	Move modified files from a temporary (working) repository on top of another (template) repository
 *	@param $param[1] temporary repository
 *	@param $param['repo'] repository to switch to
 *	@param $param['clean_before'] run "make clean" before determining the modified files (default: true)
 *	@param $param['clean_after'] run "make clean" after determining the modified files (default: true)
 */
function api_post_temp_switch_repo($param = array()) {
	$temp = $param[1];
	if (!@is_dir(tmp_dir($temp))) {
		router_error_404('Cannot get ' . $temp);
	}

	if (empty($param['repo'])) {
		router_error_400('Required parameter repo missing or empty');
	} else {
		$repo = $param['repo'];
	}

	// client can specify whether to run "make clean" before or after the switch
	if (isset($param['clean_before'])) {
		$clean_before = (bool)$param['clean_before'];
	} else {
		$clean_before = true;
	}
	if (isset($param['clean_after'])) {
		$clean_after = (bool)$param['clean_after'];
	} else {
		$clean_after = true;
	}

	// create a new repository under a temporary name
	$staging = get_repo($repo);
	if ($staging === false) {
		router_error_404('Cannot get ' . $repo);
	}

	// clean, if requested
	if ($clean_before) {
		make_run($temp, 'clean');
	}

	// copy the modified files over
	$old_umask = @umask(0000);
	foreach (repo_get_modified_files($temp) as $fn) {
		// make sure the containing directories exist
		create_containing_dir(tmp_dir($staging) . '/' . $fn);
		// copy
		@copy(tmp_dir($temp) . '/' . $fn, tmp_dir($staging) . '/' . $fn);
	}
	@umask($old_umask);

	// remove original repository
	if (false === rm_recursive(tmp_dir($temp))) {
		router_error_500('Cannot delete ' . $temp);
	}

	// move new repository to location of original repository
	if (false === @rename(tmp_dir($staging), tmp_dir($temp))) {
		router_error_500('Cannot rename ' . $staging . ' to ' . $temp);
	}

	// clean, if requested
	if ($clean_after) {
		make_run($temp, 'clean');
	}

	return true;
}


/**
 *	Delete a temporary (working) repository
 *	@param $param[1] temporary repository
 */
function api_post_temp_delete($param = array()) {
	$temp = $param[1];
	if (!@is_dir(tmp_dir($temp))) {
		router_error_404('Cannot get ' . $temp);
	}

	if (false === rm_recursive(tmp_dir($temp))) {
		router_error_500('Cannot delete ' . $temp);
	}

	return true;
}

function checkUrl($url) {
    // Simple check
    if (!$url) { return FALSE; }
    // Create cURL resource using the URL string passed in
    $curl_resource = curl_init($url);
    // Set cURL option and execute the "query"
    curl_setopt($curl_resource, CURLOPT_RETURNTRANSFER, true);
    curl_exec($curl_resource);
    // Check for the 404 code (page must have a header that correctly display 404 error code according to HTML standards
    if( in_array(curl_getinfo($curl_resource, CURLINFO_HTTP_CODE), array(301, 302, 404) ) ) {
        // Code matches, close resource and return false
        curl_close($curl_resource);
        return FALSE;
    } else {
        // No matches, close resource and return true
        curl_close($curl_resource);
        return TRUE;
    }
}

function api_get_clean_projects($param = array()) {
	$str = @file_get_contents(rtrim(config('content_dir', 'content'), '/') . '/projects.json');
	$json = @json_decode($str);
	if (!is_array($json)) {
		return array();
	} else {
		$len = count($json);
		for ($i=$len; $i>=1; $i--){
			$project = $json[$i-1];
			if( isset($project->github_repo) ) {
				$url = "https://github.com/" . $project->github_repo;
				if(checkUrl($url) == false) {
					array_splice($json, $i-1, 1);
				}
			}
		}
		
		// save
        // XXX (later): create helper functions, make atomic
        $str = @json_encode($json);
        if (false === file_put_contents(rtrim(config('content_dir', 'content'), '/') . '/projects.json', $str)) {
            router_error_500('Cannot save projects.json');
        }
        
		return $json;
	}
}

/**
 *	Get a list of projects registered with the system
 */
function api_get_projects($param = array()) {
	$str = @file_get_contents(rtrim(config('content_dir', 'content'), '/') . '/projects.json');
	$json = @json_decode($str);
	if (!is_array($json)) {
		return array();
	} else {
		// XXX (later): filter email addresses etc, also add .htacess rule to prevent direct access
		return $json;
	}
}


/**
 *	Register or update a project with the system
 *	@param $param[1] remote repository
 *	@param $param[...] additional key -> value pairs
 */
function api_post_project_create($param = array()) {
	$repo = $param[1];

	// load
	$str = @file_get_contents(rtrim(config('content_dir', 'content'), '/') . '/projects.json');
	$json = @json_decode($str);
	if (!is_array($json)) {
		$json = array();
	}

	// check if the repo already exists
	$project = NULL;
	for ($i=0; $i < count($json); $i++) {
		if (isset($json[$i]->repo) && $json[$i]->repo === $repo) {
			$project = $json[$i];
			break;
		}
	}
	if (!$project) {
		$project = new stdClass();
		$project->repo = $repo;
		$project->created = time();
		$json[] = $project;
	}

	// add additional values
	foreach ($param as $key => $val) {
		// ignore router arguments
		if (is_int($key)) {
			continue;
		}
		$project->$key = $val;
	}
	$project->updated = time();

	// save
	// XXX (later): create helper functions, make atomic
	$str = @json_encode($json);
	$old_umask = @umask(0000);
	if (false === file_put_contents(rtrim(config('content_dir', 'content'), '/') . '/projects.json', $str)) {
		@umask($old_umask);
		router_error_500('Cannot save projects.json');
	}
	@umask($old_umask);

	return true;
}


/**
 *	Unregister a project with the system
 *	@param $param[1] remote repository
 */
function api_post_project_delete($param = array()) {
	$repo = $param[1];

	// load
	$str = @file_get_contents(rtrim(config('content_dir', 'content'), '/') . '/projects.json');
	$json = @json_decode($str);
	if (!is_array($json)) {
		$json = array();
	}

	// search for repo
	$found = false;
	for ($i=0; $i < count($json); $i++) {
		if (isset($json[$i]->repo) && $json[$i]->repo === $repo) {
			array_splice($json, $i, 1);
			$found = true;
			break;
		}
	}

	if (!$found) {
		router_error_404('Cannot find project ' . $repo);
	}

	// save
	$str = @json_encode($json);
	$old_umask = @umask(0000);
	if (false === file_put_contents(rtrim(config('content_dir', 'content'), '/') . '/projects.json', $str)) {
		@umask($old_umask);
		router_error_500('Cannot save projects.json');
	}
	@umask($old_umask);

	return true;
}



register_route('GET' , 'repos', 'api_get_repos');
register_route('GET' , 'repos/files/raw/(.+)', 'api_get_repo_files_raw');
register_route('GET' , 'repos/files/(.+)', 'api_get_repo_files');
register_route('GET' , 'repos/targets/(.+)', 'api_get_repo_targets');
register_route('GET' , 'temps', 'api_get_temps');
register_route('POST', 'temps/create', 'api_post_temp_create');
register_route('GET' , 'temps/([0-9]+)', 'api_get_temp');
register_route('GET' , 'temps/files/([0-9]+)/(.+)', 'api_get_temp_file');
register_route('POST', 'temps/files/update/([0-9]+)', 'api_post_temp_files_update');
register_route('POST', 'temps/files/upload/([0-9]+)', 'api_post_temp_files_upload');
register_route('POST', 'temps/files/delete/([0-9]+)/(.+)', 'api_post_temp_files_delete');
register_route('POST', 'temps/make/([0-9]+)/?(.*)', 'api_post_temp_make');
register_route('POST', 'temps/commit/([0-9]+)', 'api_post_temp_commit');
register_route('POST', 'temps/push/([0-9]+)', 'api_post_temp_push');
register_route('POST', 'temps/switch_repo/([0-9]+)', 'api_post_temp_switch_repo');
register_route('POST', 'temps/delete/([0-9]+)', 'api_post_temp_delete');
register_route('GET' , 'projects', 'api_get_projects');
register_route('GET' , 'clean_projects', 'api_get_clean_projects');
register_route('POST', 'projects/create/(.+)', 'api_post_project_create');
register_route('POST', 'projects/update/(.+)', 'api_post_project_create');
register_route('POST', 'projects/delete/(.+)', 'api_post_project_delete');


$query = $_SERVER['QUERY_STRING'];
// use the first URL argument for the router
$pos = strpos($query, '&');
if ($pos !== false) {
	$query = substr($query, 0, $pos);
}
// return JSON by default
@header('Content-type: application/json; charset=utf-8');
echo json_encode(route($_SERVER['REQUEST_METHOD'], $query));
