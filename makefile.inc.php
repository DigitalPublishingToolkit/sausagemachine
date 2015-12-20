<?php

/*
	Makefile parsing and execution for Sausage Machine
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
 *	Return all targets defined in a Makefile
 *	@param $dir directory to check for a Makefile
 *	@return array of targets, or false in case of error
 */
function make_get_targets($dir) {
	$fn = rtrim($dir, '/') . '/' . 'Makefile';
	if (false === is_file($fn)) {
		$fn = rtrim($dir, '/') . '/' . 'makefile';
		if (false === is_file($fn)) {
			return false;
		}
	}

	$s = @file_get_contents($fn);
	if ($s === false) {
		return false;
	}

	// Note: this currently doesn't correctly evaluate targets such as
	// "arm7/$(TARGET).elf"
	// XXX (later): parse the output of "make -pn" instead

	$lines = explode("\n", $s);
	$targets = array();

	foreach ($lines as $line) {
		$colon = strpos($line, ':');
		$equal = strpos($line, '=');
		$hash = strpos($line, '#');

		if (strlen($line) == 0) {
			// empty
			continue;
		} else if ($line[0] === "\t") {
			// action line
			continue;
		} else if (substr($line, 0, 8) === '        ') {
			// action line (8+ spaces)
			continue;
		} else if ($colon !== false) {
			// potential target, check if comment
			if ($hash !== false && $hash < $colon) {
				continue;
			}
			// or a variable assignment
			if ($equal !== false) {
				continue;
			}
			$target = trim(substr($line, 0, $colon));
			// prevent duplicates
			if (!in_array($target, $targets)) {
				$targets[] = $target;
			}
		}
	}

	return $targets;
}

/**
 *	Execute a Makefile
 *	@param $dir directory to execute make in
 *	@param $target Makefile target (defaults to "all")
 *	@param &$out will be set with make output
 *	@return return value of make (zero is success)
 */
function make_run($dir, $target = 'all', &$out = '') {
	$old_cwd = getcwd();
	@chdir($dir);
	// XXX (later): support more than one target
	@exec('make ' . escapeshellarg($target) . ' 2>&1', $out, $ret_val);
	@chdir($old_cwd);
	return $ret_val;
}
