<?php

/*
 * Don't modify configuration values in this file, as it will be
 * overwritten by updates. Instead, create a new file named
 * user_config.inc.php in the same directory, and use it to set
 * values in the same manner as it done here.
 */

$config = array();

// Defaults
error_reporting(E_ALL);

$config['content_dir'] = 'content';		// path to store content, must be writable for the webserver process

// Overwrites
@include('user_config.inc.php');


/**
 *	Return the value of a configuration option
 *	@param $key configuration option
 *	@param $default default value to return if option is not set
 *	@return value
 */
function config($key, $default = false) {
	global $config;
	if (isset($config[$key])) {
		return $config[$key];
	} else {
		return $default;
	}
}
