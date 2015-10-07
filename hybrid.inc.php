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
	$commit = repo_commit($tmp, "Test\ncommit: \"yay\"");
	$push = repo_push($tmp, 'ssh://git@github.com/gohai/test.git');		// supposed to return NULL after the first time
	$rewind = repo_rewind($tmp, 1);
	$release = release_repo($tmp);
	return array('tmp' => $tmp, 'changed' => $changed, 'stage' => $stage, 'commit' => $commit, 'push' => $push, 'rewind' => $rewind, 'release' => $release);
}
