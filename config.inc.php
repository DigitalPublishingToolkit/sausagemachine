<?php

/*
 * Don't modify configuration values in this file, as it will be
 * overwritten by updates. Instead, create a new file named
 * user_config.inc.php in the same directory, and use it to set
 * values in the same manner as done here.
 */

$config = array();

// enable error reporting (useful for development)
error_reporting(E_ALL);

// path to store content, must be writable for the webserver process
$config['content_dir'] = 'content';
// default repository used for the web interface
$config['default_repo'] = 'https://github.com/DigitalPublishingToolkit/template-test.git';
// default target selected on the web interface
$config['default_target'] = 'html';
// default view to display
$config['default_route'] = 'edit';
// GitHub application "Client ID (to generate: https://github.com/settings/applications/new)
$config['github_client_id'] = 'CHANGEME';
// GitHub application "Client Secret" matching the "Client ID" above
$config['github_client_secret'] = 'CHANGEME';
// GitHub username used for committing to user repositories
$config['github_push_as'] = 'CHANGEME';
// GitHub username associated with the application
$config['github_useragent'] = 'CHANGEME';
// blacklist of Makefile targets to hide from the web interface
$config['ignore_targets'] = array(
		'all',
		'book.md',
		'clean',
		'folders',
		'markdowns',
		'scribus',
		'test'
	);
// time in seconds after we re-fetch repositories rather than relying on the cache
$config['repo_cache_time'] = 3600;
// maximum number of repositories being kept in cache (discards least recently used), 0 to disable
$config['repo_max_cached'] = 50;
// list of all (template) repositories available in the web interface
$config['repos'] = array(
		array(
			'repo' => 'https://github.com/DigitalPublishingToolkit/template-test.git',
			'description' => 'PublishingLab default'
		),
		array (
			'repo' => 'https://github.com/template01/template01-template-test.git',
			'description' => 'template01'
		)
	);
// readable titles for various Makefile targets
$config['target_descriptions'] = array(
		'book.epub' => 'EPUB',
		'html' => 'Markdown as HTML',
		'icmls' => 'ICML for InDesign'
	);
// time in seconds before temporary (working) repositories get deleted
$config['temp_max_age'] = 3600;

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
