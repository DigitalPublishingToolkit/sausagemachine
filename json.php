<?php

@require_once('config.inc.php');
require_once('hybrid.inc.php');
require_once('router.inc.php');

// run router and return result as JSON string
$ret = router($_SERVER['QUERY_STRING'], $_SERVER['REQUEST_METHOD']);
@header('Content-type: application/json; charset=utf-8');
echo json_encode($ret);
