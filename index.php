<?php

@require_once('config.inc.php');
require_once('github.inc.php');
require_once('hybrid.inc.php');
require_once('router.inc.php');

// run router and return result as HTML
$desired_route = $_SERVER['QUERY_STRING'];
if (empty($desired_route)) {
	$desired_route = config('default_route', '');
}

$ret = router($desired_route, $_SERVER['REQUEST_METHOD']);
echo $ret;
