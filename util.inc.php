<?php

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
 *	Return a file's mime type
 *	@param string $fn file name
 *	@return string if successful, false if not
 */
function get_mime($fn) {
	$finfo = @finfo_open(FILEINFO_MIME_TYPE);
	$out = @finfo_file($finfo, $fn);
	@finfo_close($finfo);
	return $out;
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
