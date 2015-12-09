<?php

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
	// XXX: make -pn

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
	// XXX: support more than one target
	@exec('make ' . escapeshellarg($target) . ' 2>&1', $out, $ret_val);
	@chdir($old_cwd);
	$out = implode("\n", $out);
	return $ret_val;
}
