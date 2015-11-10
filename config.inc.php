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

// path to store content, must be writable for the webserver process
$config['content_dir'] = 'content';
// default repository used for the web interface
$config['default_repo'] = 'https://github.com/DigitalPublishingToolkit/template-test.git';
// default target selected on the web interface
$config['default_target'] = 'html';
// time in seconds after we re-fetch repositories rather than relying on the cache
$config['repo_cache_time'] = 3600;
// list of all repositories available on the web interface
$config['repos'] = array(
		array(
			'url' => 'https://github.com/DigitalPublishingToolkit/template-test.git',
			'description' => 'Default template'
		)
	);

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
