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
// default view to display
$config['default_route'] = 'import';
// Github application "Client ID" (to generate: https://github.com/settings/applications/new)
$config['github_client_id'] = 'CHANGEME';
// Github application "Client Secret" matching the "Client ID above"
$config['github_client_secret'] = 'CHANGEME';
// blacklist of Makefile targets to hide from being displayed
$config['ignore_targets'] = array(
		'book.md',
		'clean',
		'folders',
		'markdowns',
		'scribus',
		'test'
	);
// time in seconds after we re-fetch repositories rather than relying on the cache
$config['repo_cache_time'] = 3600;
// list of all repositories available on the web interface
$config['repos'] = array(
		array(
			'repo' => 'https://github.com/DigitalPublishingToolkit/template-test.git',
			'description' => 'Default template'
		)
	);
$config['target_descriptions'] = array(
		'book.epub' => 'EPUB',
		'html' => 'Markdown as HTML',
		'icmls' => 'ICML files for use with Adobe InDesign'
	);

// Overwrites
@include('user-config.inc.php');


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
