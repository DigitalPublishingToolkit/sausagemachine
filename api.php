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
 *	Get a file from a temporary (working) directory
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
	}
	$path = tmp_dir($temp) . '/' . $fn;

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


register_route('GET' , 'repos', 'api_get_repos');
register_route('GET' , 'repos/targets/(.+)', 'api_get_repo_targets');
register_route('GET' , 'temps', 'api_get_temps');
register_route('POST', 'temps/create', 'api_post_temp_create');
register_route('GET' , 'temps/([0-9]+)', 'api_get_temp');
register_route('GET' , 'temps/files/([0-9]+)/(.+)', 'api_get_temp_file');



$query = $_SERVER['QUERY_STRING'];
// use the first URL argument for the router
$pos = strpos($query, '&');
if ($pos !== false) {
	$query = substr($query, 0, $pos);
}
// return JSON by default
@header('Content-type: application/json; charset=utf-8');
echo json_encode(route($_SERVER['REQUEST_METHOD'], $query));
