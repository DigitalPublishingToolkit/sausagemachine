<?php

// XXX: check

// copy the content of $src into $dst
// not checked: symlinks, umask
function cp_recursive($src, $dst) {
	if (@is_file($src)) {
		return @copy($src, $dst);
	} else if (@is_dir($src)) {
		if (($childs = @scandir($src)) === false) {
			return false;
		}
		if (!@is_dir(rtrim($dst, '/'))) {
			if (false === @mkdir(rtrim($dst, '/'))) {
				return false;
			}
		}
		$success = true;
		foreach ($childs as $child) {
			if ($child == '.' || $child == '..') {
				continue;
			}
			if (false === cp_recursive(rtrim($src, '/').'/'.$child, rtrim($dst, '/').'/'.$child)) {
				$success = false;
			}
		}
		return $success;
	}
}

// XXX: taken from Hotglue

/**
 *	delete a file or directory
 *
 *	@param string $f file name
 *	@return true if successful, false if not
 */
function rm_recursive($f)
{
	if (@is_file($f) || @is_link($f)) {
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
				rm_recursive($f.'/'.$child);
			}
		}
		return @rmdir($f);
	}
}
