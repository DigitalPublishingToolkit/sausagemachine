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

@require_once('config.inc.php');
require_once('git.inc.php');
require_once('makefile.inc.php');
require_once('router.inc.php');


/**
 *	Get a list of (template) repositories
 */
function api_get_repos($param = array()) {
	$repos = config('repos', array());
	foreach ($repos as &$repo) {
		// mark the default repo
		if ($repo['repo'] === config('default_repo')) {
			$repo['default'] = true;
		}
	}
	return $repos;
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
		// XXX: implement
		'commit' => '',
		'files' => repo_get_all_files($temp)
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
		// XXX: implement
		'commit' => '',
		'files' => repo_get_all_files($temp),
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
		@header('Content-Type: ' . get_mime($path));
		@header('Content-Length: ' . @filesize($path));
		@readfile($path);
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
		$pos = strrpos($file['fn'], '/');
		if ($pos !== false) {
			@mkdir(tmp_dir($temp) . '/' . substr($file['fn'], 0, $pos), 0777, true);
		}

		// save base64-encoded string as file
		if (false !== @file_put_contents(tmp_dir($temp) . '/' . $file['fn'], @base64_decode($file['data']))) {
			$modified[] = $file['fn'];
		}
	}
	@umask($old_umask);
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


// XXX: move to hybrid
function get_uploaded_file_dest_fn($tmp_key, $orig_fn, $mime, $tmp_fn) {
	// many relevant file formats still arive as "application/octet-stream"
	// so ignore the MIME type for now, and focus solely on the extension
	// of the original filename we got from the browser
	$ext = strtolower(filext($orig_fn));

	switch ($ext) {
		case 'css':
			// CSS
			// XXX: implement in template
			return 'epub/custom.css';
		case 'docx':
			// Word document
			return 'docx/' . basename($orig_fn);
		case 'gif':
		case 'png':
		case 'jpeg':
		case 'jpg':
			// image
			if ($orig_fn === 'cover.jpg') {
				// special case for the cover image
				// XXX: make template accept .gif, .png, .jpeg as well
				return 'epub/cover.jpg';
			} else {
				return 'md/imgs/' . basename($orig_fn);
			}
		case 'md':
			return 'md/' . basename($orig_fn);
		case 'otf':
		case 'ttf':
		case 'woff':
		case 'woff2':
			// font
			return 'lib/' . basename($orig_fn);
		default:
			break;
	}

	// not supported
	return false;
}


// XXX: move to hybrid
function inject_uploaded_file($tmp_key, $file, $auto_convert = true) {
	// establish destination filename
	$dest_fn = get_uploaded_file_dest_fn($tmp_key, $file['name'], $file['type'], $file['tmp_name']);
	if ($dest_fn === false) {
		return array();
	}

	// make sure the containing directories exist
	// XXX: make this a function in util
	$pos = strrpos($dest_fn, '/');
	if ($pos !== false) {
		@mkdir(tmp_dir($tmp_key) . '/' . substr($dest_fn, 0, $pos), 0777, true);
	}

	// move to destination
	if (false === @move_uploaded_file($file['tmp_name'], tmp_dir($tmp_key) . '/' . $dest_fn)) {
		return array();
	}

	if ($auto_convert) {
		// convert Word documents instantaneously to Markdown
		if (filext($dest_fn) === 'docx') {
			// XXX
		}
	}

	return array($dest_fn);
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


// XXX: doxygen
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
	// XXX: continue here
	$modified = array();

	if ($clean_after) {
		make_run(tmp_dir($temp), 'clean');
	}

	return array(
		'target' => $target,
		'modified' => $modified,
		'error' => ($ret_val === 0) ? false : $ret_val,
		'out' => $out
	);
}



register_route('GET' , 'repos', 'api_get_repos');
register_route('GET' , 'repos/targets/(.+)', 'api_get_repo_targets');
register_route('GET' , 'temps', 'api_get_temps');
register_route('POST', 'temps/create', 'api_post_temp_create');
register_route('GET' , 'temps/([0-9]+)', 'api_get_temp');
register_route('GET' , 'temps/files/([0-9]+)/(.+)', 'api_get_temp_file');
register_route('POST', 'temps/files/update/([0-9]+)', 'api_post_temp_files_update');
register_route('POST', 'temps/files/upload/([0-9]+)', 'api_post_temp_files_upload');
register_route('POST', 'temps/files/delete/([0-9]+)/(.+)', 'api_post_temp_files_delete');
// XXX: POST
register_route('GET' , 'temps/make/([0-9]+)/?(.*)', 'api_post_temp_make');


$query = $_SERVER['QUERY_STRING'];
// use the first URL argument for the router
$pos = strpos($query, '&');
if ($pos !== false) {
	$query = substr($query, 0, $pos);
}
// return JSON by default
@header('Content-type: application/json; charset=utf-8');
echo json_encode(route($_SERVER['REQUEST_METHOD'], $query));
