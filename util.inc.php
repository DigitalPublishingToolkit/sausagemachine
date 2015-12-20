<?php

/*
	Portable utility functions for Sausage Machine
	Partially taken from Hotglue
	Copyright (C) 2010  Gottfried Haider, Danja Vasiliev
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

/**
 *	Copy the content of a directory
 *	@param $src path to a file or directory
 *	@param $dst path to a file or directory
 *	@return true if successful, false if not
 */
function cp_recursive($src, $dst) {
	if (is_file($src)) {
		return @copy($src, $dst);
	} else if (is_dir($src)) {
		if (($childs = @scandir($src)) === false) {
			return false;
		}
		if (!is_dir(rtrim($dst, '/'))) {
			if (false === @mkdir(rtrim($dst, '/'))) {
				return false;
			}
		}
		$success = true;
		foreach ($childs as $child) {
			if ($child == '.' || $child == '..') {
				continue;
			}
			if (false === cp_recursive(rtrim($src, '/') . '/' . $child, rtrim($dst, '/') . '/' . $child)) {
				$success = false;
			}
		}
		return $success;
	}
}


/**
 *	Return a file's extension
 *	@param string $fn file name
 *	@return string, excluding the dot
 */
function filext($fn) {
	$pos = strrpos($fn, '.');
	if ($pos !== false) {
		return substr($fn, $pos+1);
	} else {
		return '';
	}
}


/**
 *	Return a file's mime type
 *	@param string $fn file name
 *	@return string if successful, false if not
 */
function get_mime($fn) {
	if (function_exists('finfo_open')) {
		$finfo = @finfo_open(FILEINFO_MIME_TYPE);
		$out = @finfo_file($finfo, $fn);
		@finfo_close($finfo);
		return $out;
	} else {
		return mime_content_type($fn);
	}
}


function list_files_recursive($base_dir, $cur_dir = '') {
	$dir = rtrim($base_dir, '/');
	if (!empty($cur_dir)) {
		$dir .= '/' . $cur_dir;
	}

	$fns = @scandir($dir);
	if ($fns === false) {
		return false;
	}

	$ret = array();
	foreach ($fns as $fn) {
		if (in_array($fn, array('.', '..'))) {
			continue;
		}
		if (@is_dir($dir . '/' . $fn)) {
			$childs = list_files_recursive($base_dir, $cur_dir . '/' . $fn);
			if (is_array($childs)) {
				$ret = array_merge($ret, $childs);
			}
		} else {
			$ret[] = ltrim($cur_dir . '/' . $fn, '/');
		}
	}
	return $ret;
}


/**
 *	Render a PHP view and return the output as a string
 *	@param string $fn filename
 *	@param $data variable to pass to the view
 *	@return string
 */
function render_php($fn, $data = array()) {
	@ob_start();
	@include($fn);
	$ret = @ob_get_contents();
	@ob_end_clean();
	return $ret;
}


/**
 *	Delete a file or directory
 *
 *	Function taken from Hotglue's util.inc.php.
 *	@param string $f file name
 *	@return true if successful, false if not
 */
function rm_recursive($f)
{
	if (is_file($f) || is_link($f)) {
		// note: symlinks get deleted right away, and not recursed into
		return @unlink($f);
	} else {
		if (($childs = @scandir($f)) === false) {
			return false;
		}
		// strip a tailing slash
		if (substr($f, -1) == '/') {
			$f = substr($f, 0, -1);
		}
		foreach ($childs as $child) {
			if ($child == '.' || $child == '..') {
				continue;
			} else {
				rm_recursive($f . '/' . $child);
			}
		}
		return @rmdir($f);
	}
}
