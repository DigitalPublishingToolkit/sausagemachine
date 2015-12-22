<?php

/*
	Various (tweakable) heuristics for Sausage Machine
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
require_once('util.inc.php');


/**
 *	Get the version string of the Pandoc executable
 *	@return string or false if not available
 */
function get_pandoc_version() {
	// fix "pandoc: HOME: getAppUserDataDirectory: does not exist (no environment variable)" on webservers
	// that don't have $_SERVER['HOME'] set
	// see also https://github.com/jgm/pandoc-citeproc/issues/35
	// not sure if this is also needed for regular "make" invocations
	@exec('HOME=' . escapeshellarg(get_server_home_dir()) . ' pandoc -v 2>&1', $out, $ret_val);
	if ($ret_val !== 0) {
		return false;
	} else {
		return trim(substr($out[0], 7));
	}
}


function get_uploaded_file_dest_fn($tmp_key, $orig_fn, $mime, $tmp_fn) {
	// many relevant file formats still arive as "application/octet-stream"
	// so ignore the MIME type for now, and focus solely on the extension
	// of the original filename we got from the browser
	$ext = strtolower(filext($orig_fn));

	switch ($ext) {
		case 'css':
			// CSS
			// XXX (later): implement in template
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
				// XXX (later): make template accept .gif, .png, .jpeg as well
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


function inject_uploaded_file($tmp_key, $file, $auto_convert = true) {
	// establish destination filename
	$dest_fn = get_uploaded_file_dest_fn($tmp_key, $file['name'], $file['type'], $file['tmp_name']);
	if ($dest_fn === false) {
		return array();
	}

	// make sure the containing directories exist
	create_containing_dir(tmp_dir($tmp_key) . '/' . $dest_fn);

	// move to destination
	if (false === @move_uploaded_file($file['tmp_name'], tmp_dir($tmp_key) . '/' . $dest_fn)) {
		return array();
	}

	if ($auto_convert) {
		// convert Word documents instantaneously to Markdown
		$start = time();
		if (filext($dest_fn) === 'docx') {
			make_run(tmp_dir($tmp_key), 'markdowns');
			// XXX (later): run "make clean" here?
		}
		$modified_after = repo_get_modified_files_after($tmp_key, $start-1);
		// make sure the destination filename is part of th array
		if (is_array($modified_after) && !in_array($dest_fn, $modified_after)) {
			$modified_after[] = $dest_fn;
		}
		return $modified_after;
	}

	return array($dest_fn);
}
