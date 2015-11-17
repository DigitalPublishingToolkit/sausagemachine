<?php

@require_once('config.inc.php');
require_once('git.inc.php');
require_once('makefile.inc.php');

/**
 *	Return the edit view
 */
function route_get_edit() {
	return render_php('view-edit.php');
}

/**
 *	Return the import view
 */
function route_get_import() {
	return render_php('view-import.php');
}

/**
 *	Return the projects view
 */
function route_get_projects() {
	return render_php('view-projects.php');
}

/**
 *	Return a list of (template) repositories
 */
function route_get_repos($param = array()) {
	$repos = config('repos', array());
	foreach ($repos as &$repo) {
		if ($repo['repo'] === config('default_repo')) {
			$repo['default'] = true;
		}
	}
	return $repos;
}

/**
 *	Return a list of repositories created by users
 */
function route_get_user_repos($param = array()) {
	// XXX
}

/**
 *	Convert markdown text to a user-specified Makefile target
 */
function route_get_convert($param = array()) {
	if (!@is_string($param['md'])) {
		router_bad_request('Missing or invalid argument md');
	}

	if (@is_string($param['repo'])) {
		$repo = $param['repo'];
	} else {
		$repo = config('default_repo');
	}

	if (@is_string($param['target'])) {
		$target = $param['target'];
	} else {
		$target = config('default_target');
	}

	// get a copy of the repo specified
	$tmp = get_repo($repo);
	if ($tmp === false) {
		router_internal_server_error('Cannot get a copy of ' . $repo);
	}

	// write seed.md
	if (false === @file_put_contents(tmp_dir($tmp) . '/md/seed.md', $param['md'])) {
		release_repo($tmp);
		router_internal_server_error('Cannot write markdown to repo ' . $tmp);
	}

	// convert
	$ret = make_run(tmp_dir($tmp), $target, $out);
	if ($ret !== 0) {
		// return error to the client
		release_repo($tmp);
		return(array('error' => $ret, 'output' => $out));
	}

	// see which files changed and get a base64 representation
	$changed = repo_get_modified_files($tmp);
	$out = array();
	foreach ($changed as $fn) {
		if ($fn === 'md/seed.md') {
			// ignore the file we just created
			continue;
		}
		$data = @file_get_contents(tmp_dir($tmp) . '/' . $fn);
		if ($data === false) {
			// ignore errors
			continue;
		}
		$out[] = array(
			'fn' => $fn,
			'mime' => get_mime(tmp_dir($tmp) . '/' . $fn),
			'data' => base64_encode($data)
		);
	}

	release_repo(array('generated' => $tmp));
	return $out;
}




/**
 *	Return a list of Makefile targets for a (template) repository
 */
function route_get_targets($param = array()) {
	if (@is_string($param['repo'])) {
		$repo = $param['repo'];
	} else {
		$repo = config('default_repo');
	}

	$cached = get_repo_for_reading($repo);
	if ($cached === false) {
		router_internal_server_error('Cannot get ' . $repo);
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

function route_post_upload_files($param = array()) {
	if (@is_string($param['repo'])) {
		$repo = $parm['repo'];
	} else {
		$repo = config('default_repo');
	}

	if (@is_array($param['uploaded'])) {
		$uploaded = $parm['uploaded'];
	} else {
		$uploaded = array();
	}

	if (@is_string($param['tmp_key'])) {
		$tmp_key = $parm['tmp_key'];
		// check if the repository changed
		$old_repo = repo_get_url($tmp_key);
		if ($old_repo === false) {
			router_internal_server_error('Cannot get URL for ' . $tmp_key);
		}
		if ($old_repo !== $repo) {
			if (false === handle_repo_switch($tmp_key, $repo, $uploaded)) {
				router_internal_server_error('Error switching ' . $tmp_key . ' to ' . $repo);
			}
		}
	} else {
		$tmp_key = get_repo($repo);
		if ($tmp_key === false) {
			router_internal_server_error('Cannot get ' . $repo);
		}
	}

	foreach ($_FILES as $file) {
		$renamed_file = inject_file($tmp_key, $file['tmp_name'], $file['type'], $file['name']);
		if ($renamed_file !== false) {
			$uploaded[] = $renamed_file;
		}
	}

	return array('tmp_key' => $tmp_key, 'uploaded' => $uploaded);
}

function inject_file($tmp_key, $fn, $mime = NULL, $orig_fn = NULL) {
	$old_umask = @umask(0000);

	switch ($mime) {
		// XXX: md comes across as application/octet-stream
		case 'application/font-woff':
			// supported font formats
			// XXX: other ones come across as application/octet-stream
			@mkdir(tmp_key($tmp_key) . '/lib');
			$renamed_file = array('lib/' . $orig_fn);
			$ret = @move_uploaded_file($fn, tmp_dir($tmp_key) . '/' . $renamed_file);
			break;
		case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
			// docx
			// XXX: do on the spot conversion
			// XXX: force basename() and check extension
			// make sure the directory exists
			@mkdir(tmp_dir($tmp_key) . '/docx');
			$renamed_file = array('docx/' . $orig_fn);
			$ret = @move_uploaded_file($fn, tmp_dir($tmp_key) . '/' . $renamed_file);
			break;
		case 'image/gif':
		case 'image/jpg':
		case 'image/png':
			// supported image formats
			// XXX: special case for cover
			@mkdir(tmp_dir($tmp_key) . '/md/imgs', 0777, true);
			$renamed_file = array('md/imgs/' . $orig_fn);
			$ret = @move_uploaded_file($fn, tmp_dir($tmp_key) . '/' . $renamed_file);
			break;
		case 'text/css':
			// stylesheet
			// XXX: rename
			@mkdir(tmp_dir($tmp_key) . '/epub');
			$renamed_file = array('epub/' . $orig_fn);
			$ret = @move_uploaded_file($fn, tmp_dir($tmp_key) . '/' . $renamed_file);
			break;
		default:
			// not supported
			$ret = false;
			break;
	}

	@umask($old_umask);

	return ($ret === false) ? false : $renamed_file;
}

function handle_repo_switch($tmp_key, $new_repo, &$uploaded = array()) {
	$staging = get_repo($new_repo);
	if ($staging === false) {
		router_internal_server_error('Cannot get ' . $repo);
	}
	// copy the files over
	for ($i=0; $i < count($uploaded); $i++) {
		// XXX: umask?
		if (false === @copy(tmp_dir($tmp_key) . '/' . $uploaded[$i], tmp_dir($staging) . '/' . $uploaded[$i])) {
			// ignore error, but remove from list
			array_splice($uploaded, $i, 1);
			$i--;
		}
	}
	// delete the original repository in tmp
	if (false === rm_recursive(tmp_dir($tmp_key))) {
		router_internal_server_error('Cannot delete ' . $tmp_key);
	}
	// move staging to the previous location
	if (false === @rename(tmp_dir($staging), tmp_dir($tmp_key))) {
		router_internal_server_error('Cannot rename ' . $staging . ' to ' . $tmp_key);
	}
}

//$push = repo_push($tmp, 'ssh://git@github.com/gohai/test.git');		// supposed to return NULL after the first time
