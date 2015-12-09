<?php

@require_once('config.inc.php');
require_once('git.inc.php');
require_once('makefile.inc.php');
require_once('router.inc.php');

function api_get_repos($param = array()) {
	$repos = config('repos', array());
	foreach ($repos as &$repo) {
		if ($repo['repo'] === config('default_repo')) {
			$repo['default'] = true;
		}
	}
	return $repos;
}

function api_get_repo_targets($param = array()) {
	$repo = $param[1];

	$cached = get_repo_for_reading($repo);
	if ($cached === false) {
		router_not_found('Cannot get ' . $repo);
	}

	$targets = make_get_targets(cache_dir($cached));
	if ($targets === false) {
		router_internal_server_error('Error getting Makefile targets for ' . $repo);
	}

	$ignore_targets = config('ignore_targets', array());
	$target_descriptions = config('target_descriptions', array());
	$default_target = config('default_target');

	for ($i=0; $i < count($targets); $i++) {
		if (in_array($targets[$i], $ignore_targets)) {
			array_splice($targets, $i, 1);
			$i--;
			continue;
		}

		$tmp = array('target' => $targets[$i]);
		if (isset($target_descriptions[$targets[$i]])) {
			$tmp['description'] = $target_descriptions[$targets[$i]];
		}
		if ($default_target === $targets[$i]) {
			$tmp['default'] = true;
		}
		$targets[$i] = $tmp;
	}

	return $targets;
}

function api_get_temps($param = array()) {
	$tmp = @scandir(tmp_dir());
	for ($i=0; $i < count($tmp); $i++) {
		if (in_array($tmp[$i], array('.', '..'))) {
			array_splice($tmp, $i, 1);
			$i--;
			continue;
		}
	}
	return $tmp;
}

function api_post_temp_create($param = array()) {
	if (isset($param['repo'])) {
		$repo = $param['repo'];
	} else {
		$repo = config('default_repo', '');
	}

	$temp = get_repo($repo);
	if ($temp === false) {
		router_internal_server_error('Cannot get a copy of ' . $repo);
	}

	return array(
		'temp' => $temp,
		'repo' => $repo,
		'branch' => 'master',
		// XXX
		'files' => array()
	);
}

function api_get_temp($param = array()) {
	$temp = $param[1];

	if (!@is_dir(tmp_dir($temp))) {
		router_not_found('Cannot get ' . $temp);
	}

	return array(
		'temp' => $temp,
		'repo' => repo_get_url($temp),
		'branch' => 'master',
		// XXX
		'files' => array(),
		'modified' => repo_get_modified_files($temp)
	);
}

function api_get_temp_files($param = array()) {
	$temp = $param[1];
	$fn = $param[2];
	$full_fn = tmp_dir($temp) . '/' . $fn;

	if (!is_file($full_fn)) {
		router_internal_server_error('Cannot get ' . $fn . ' in ' . $temp);
	}

	if (isset($param['format'])) {
		$format = $param['format'];
	} else {
		$format = 'raw';
	}

	if ($format === 'download') {
		// force a download
		@header('Content-Type: application/octet-stream');
		@header('Content-Disposition: attachment; filename="' . basename($fn) . '"');
		@header('Content-Length: ' . @filesize($full_fn));
		@readfile($full_fn);
		die();
	} else if ($format === 'json') {
		// serve base64-encoded as JSON
		return array(
			'fn' => $fn,
			'mime' => get_mime($full_fn),
			'data' => 	base64_encode(@file_get_contents($full_fn))
		);
	} else if ($format === 'raw') {
		// serve as file with proper MIME type
		@header('Content-Type: ' . get_mime($full_fn));
		@header('Content-Length: ' . @filesize($full_fn));
		@readfile($full_fn);
		die();
	}
}

function api_post_temp_file_update($param = array()) {
	$temp = $param[1];

	// XXX
}


register_route('GET' , 'repos', 'api_get_repos');
register_route('GET' , 'repos/targets/(.+)', 'api_get_repo_targets');
register_route('GET' , 'temps', 'api_get_temps');
register_route('POST', 'temps/create', 'api_post_temp_create');
register_route('GET' , 'temps/([0-9]+)', 'api_get_temp');
register_route('GET' , 'temps/files/([0-9]+)/(.+)', 'api_get_temp_files');


$query = $_SERVER['QUERY_STRING'];
$pos = strpos($query, '&');
if ($pos !== false) {
	$query = substr($query, 0, $pos);
}
@header('Content-type: application/json; charset=utf-8');
echo json_encode(route($_SERVER['REQUEST_METHOD'], $query));
