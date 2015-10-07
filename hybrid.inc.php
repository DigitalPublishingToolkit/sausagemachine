<?php

require_once('git.inc.php');

/**
 *	Test route
 *	@param $array unused
 *	@return array
 */
function route_get_test($param = array()) {
	$tmp = get_repo('https://github.com/DigitalPublishingToolkit/template-test.git');
	file_put_contents(tmp_dir($tmp).'/test', 'asdasd');
	$changed = repo_get_modified_files($tmp);
	$stage = repo_stage_files($tmp, $changed);
	$release = release_repo($tmp);
	return array('tmp' => $tmp, 'changed' => $changed, 'stage' => $stage, 'release' => $release);
}
