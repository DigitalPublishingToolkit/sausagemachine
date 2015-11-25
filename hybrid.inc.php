<?php

@require_once('config.inc.php');
require_once('git.inc.php');
require_once('makefile.inc.php');

/**
 *	Return the edit view
 */
function route_get_edit($param = array()) {
	return render_php('view-edit.php');
}

function route_get_files($param = array()) {
	if (empty($param['tmp_key'])) {
		router_internal_server_error('Required parameter tmp_key missing');
	}
	if (!@is_array($param['files'])) {
		router_internal_server_error('Required parameter files missing or invalid');
	}

	$ret = array();
	foreach ($param['files'] as $fn) {
		// XXX: why
		if (strpos($fn, '../')) {
			continue;
		}
		$bin = @file_get_contents(tmp_dir($param['tmp_key']) . '/' . $fn);
		if ($bin === false) {
			continue;
		}
		$ret[$fn] = array('data' => @base64_encode($bin), 'mime' => get_mime(tmp_dir($param['tmp_key']) . '/' . $fn));
	}
	return $ret;
}

/**
 *	Return the import view
 */
function route_get_import($param = array()) {
	return render_php('view-import.php');
}

/**
 *	Return projects registered with the system, either as HTML page or JSON array
 */
function route_get_projects($param = array()) {
	if (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false) {
		return render_php('view-projects.php');
	} else {
		$s = @file_get_contents(rtrim(config('content_dir', 'content'), '/') . 'projects.json');
		if ($s === false) {
			return array();
		}
		$json = @json_decode($s);
		if ($json === false) {
			return array();
		} else {
			return $json;
		}
	}
}

// XXX: route_projects_post?
function route_post_projects($param = array()) {

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
 *	Return a list of Makefile targets for a (template) repository
 */
function route_get_targets($param = array()) {
	if (!empty($param['repo'])) {
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

/**
 *	Return a list of repositories created by users
 */
function route_get_user_repos($param = array()) {
	// XXX
}


function route_post_upload_files($param = array()) {
	if (!empty($param['tmp_key'])) {
		$tmp_key = $param['tmp_key'];
	} else {
		// new temporary repository
		$repo = !empty($param['repo']) ? $param['repo'] : config('default_repo');
		$tmp_key = get_repo($repo);
		if ($tmp_key === false) {
			router_internal_server_error('Cannot get a copy of ' . $repo);
		}
	}

	// add uploaded files
	$uploaded = array();
	$generated = array();
	foreach ($_FILES as $file) {
		$injected = inject_uploaded_file($tmp_key, $file['tmp_name'], $file['type'], $file['name']);
		// ignore errors
		if ($injected !== false) {
			$uploaded = array_merge($uploaded, $injected['uploaded']);
			$generated = array_merge($generated, $injected['generated']);
		}
	}

	return array(
		'tmp_key' => $tmp_key,
		'repo' => repo_get_url($tmp_key),
		'uploaded' => $uploaded,
		'generated' => $generated,
		'files' => repo_get_modified_files($tmp_key)
	);
}


function route_post_convert($param = array()) {
	if (!empty($param['tmp_key'])) {
		$tmp_key = $param['tmp_key'];
		if (!empty($param['repo'])) {
			if (false === check_repo_switch($tmp_key, $param['repo'])) {
				router_internal_server_error('Error switching ' . $tmp_key . ' to ' . $param['repo']);
			}
		}
	} else {
		// new temporary repository
		$repo = !empty($param['repo']) ? $param['repo'] : config('default_repo');
		$tmp_key = get_repo($repo);
		if ($tmp_key === false) {
			router_internal_server_error('Cannot get a copy of ' . $repo);
		}
	}

	// clean repository
	make_run(tmp_dir($tmp_key), 'clean');

	// add updated files
	$files = @is_array($param['files']) ? $param['files'] : array();
	foreach ($files as $fn => $content) {
		// ignore errors
		inject_file($tmp_key, $fn, $content);
	}

	// make target
	$target = !empty($param['target']) ? $param['target'] : config('default_target');
	// timestamp for determining modified files, see below
	$then = time();
	$ret = make_run(tmp_dir($tmp_key), $target, $out);
	if ($ret !== 0) {
		// return the error in JSON instead as a HTTP status code
		return array(
			'error' => $out
		);
	}

	// established modified files
	$modified = repo_get_modified_files($tmp_key);
	$generated = array();
	foreach ($modified as $fn) {
		if (in_array($fn, array_keys($files))) {
			// we uploaded this ourselves
			continue;
		}
		// check file modification time
		$mtime = @filemtime(tmp_dir($tmp_key) . '/' . $fn);
		if ($mtime < $then) {
			// file existed before
			continue;
		}
		// XXX: fix template instead
		if (in_array($fn, array('md/book.md'))) {
			continue;
		}
		$generated[] = $fn;
	}

	return array(
		'tmp_key' => $tmp_key,
		'repo' => repo_get_url($tmp_key),
		'target' => $target,
		'generated' => $generated,
		'files' => $modified
	);
}


function inject_uploaded_file($tmp_key, $fn, $mime = NULL, $orig_fn = '') {
	// many relevant file formats still arive as "application/octet-stream"
	// so ignore the MIME type for now, and focus solely on the extension
	// of the original filename we got from the browser
	$ext = strtolower(filext($orig_fn));

	switch ($ext) {
		case 'css':
			// CSS
			$target = 'epub/custom.css';
			break;
		case 'docx':
			// Word document
			$target = 'docx/' . basename($orig_fn);
			break;
		case 'gif':
		case 'png':
		case 'jpeg':
		case 'jpg':
			// Image
			if (basename($orig_fn, '.' . $ext) == 'cover') {
				// special case for the cover image
				// delete any existing one
				$epub_dir = @scandir(tmp_dir($tmp_key) . '/epub');
				if (is_array($epub_dir)) {
					foreach ($epub_dir as $fn) {
						if (in_array($fn, array('cover.gif', 'cover.png', 'cover.jpeg', 'cover.jpg'))) {
							@unlink(tmp_dir($tmp_key) . '/epub/' . $fn);
						}
					}
				}
				$target = 'epub/' . basename($orig_fn);
			} else {
				$target = 'md/imgs/ ' . basename($orig_fn);
			}
			break;
		case 'md':
			// Markdown
			$target = 'md/' . basename($orig_fn);
			break;
		case 'otf':
		case 'tty':
		case 'woff':
		case 'woff2':
			// Font
			$target = 'lib/' . basename($orig_fn);
			break;
		default:
			$target = false;
			break;
	}

	if ($target === false) {
		// not supported
		return false;
	}

	// create files and directories as permissive as possible
	$old_umask = @umask(0000);

	// make sure the containing directory exists
	$pos = strrpos('/', $target);
	if ($pos !== false) {
		@mkdir(tmp_dir($tmp_key) . '/' . substr($target, 0, $pos), 0777, true);
	}

	// move file to final location
	$ret = @move_uploaded_file($fn, tmp_dir($tmp_key) . '/' . $target);

	// do an instant conversion for docx
	$generated = array();
	if ($ext === 'docx') {
		$modified_before = repo_get_modified_files($tmp_key);
		make_run(tmp_dir($tmp_key), 'markdowns', $out);
		foreach (repo_get_modified_files($tmp_key) as $fn) {
			if (in_array($fn, $modified_before)) {
				continue;
			}
			$generated[] = $fn;
		}
	}

	@umask($old_umask);

	return ($ret) ? array('uploaded' => array($target), 'generated' => $generated) : false;
}


function inject_file($tmp_key, $fn, $content) {
	$old_umask = @umask(0000);

	// check for possible attempts to get out of the sausage machine
	$pos = strpos('../', $fn);
	if ($pos !== false) {
		return false;
	}

	// make sure the containing directory exists
	$pos = strrpos('/', $fn);
	if ($pos !== false) {
		@mkdir(tmp_dir($tmp_key) . '/' . substr($fn, 0, $pos), 0777, true);
	}

	$ret = @file_put_contents(tmp_dir($tmp_key) . '/' . $fn, $content);

	@umask($old_umask);

	return $ret;
}


function check_repo_switch($tmp_key, $repo) {
	// XXX: implement
	return true;
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
