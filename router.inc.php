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
	$tmp = explode('&', $query_string);

	// get desired route (first in query string)
	$route = $tmp[0];

	// setup array of all other arguments
	$args = array();
	for ($i=1; $i < count($tmp); $i++) {
		// look for key=val
		$arg = explode('=', $tmp[$i]);
		if (1 < count($arg)) {
			$args[$arg[0]] = implode('=', array_slice($arg, 1));
		} else {
			$args[$arg[0]] = '';
		}
	}

	// add post arguments as well if set
	if (is_array($_POST)) {
		foreach ($_POST as $key => $val) {
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
	die();
}
