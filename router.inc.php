<?php

/**
 *	Route client request
 *
 *	If the route does not exist, the function will send a HTTP error
 *	400 and stop execution.
 *	@param $query_string most likely $_SERVER['QUERY_STRING']
 *	@param $method most likely $_SERVER['REQUEST_METHOD']
 *	@return return value from function being invoked
 */
function router($query_string, $method = 'GET') {
	// check if there is an explicit route argument
	// this is needed e.g. for GitHub callbacks, which prepend the URL with their own arguments
	if (!empty($_REQUEST['route'])) {
		$route = $_REQUEST['route'];
	} else {
		// take the first argument in the query string
		$tmp = explode('&', $query_string);
		$tmp[0] = rtrim($tmp[0], '=');
		$route = $tmp[0];
	}

	// setup array of all other arguments
	$args = array();
	if (@is_array($_REQUEST)) {
		foreach ($_REQUEST as $key => $val) {
			$args[$key] = $val;
		}
	}

	// execute route if it exists
	$func = 'route_' . strtolower($method) . '_' . $route;
	if (function_exists($func)) {
		$ret = $func($args);
	} else {
		router_bad_request('Route ' . $route . ' does not exist');
	}

	// return result
	return $ret;
}

/**
 *	Output a HTTP error 400
 *
 *	This function does not return.
 *	@param $reason reason for eventual logging (not implemented yet)
 */
function router_bad_request($reason = '') {
	@header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
	echo $reason;
	die();
}

/**
 *	Output a HTTP error 500
 *
 *	This function does not return.
 *	@param $reason reason for eventual logging (not implemented yet)
 */
function router_internal_server_error($reason = '') {
	@header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
	echo $reason;
	die();
}
