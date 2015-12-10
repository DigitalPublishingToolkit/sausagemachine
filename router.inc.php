<?php

// XXX: new way
$routes = array();

function register_route($verb, $pattern, $func) {
	global $routes;
	$verb = strtoupper($verb);

	if (!@is_array($routes[$verb])) {
		$routes[$verb] = array();
	}

	$routes[$verb][$pattern] = $func;
}

function route($verb, $url, $param = array()) {
	global $routes;
	$verb = strtoupper($verb);

	if (!@is_array($routes[$verb])) {
		return false;
	}

	foreach ($_REQUEST as $key => $val) {
		$param[$key] = $val;
	}

	foreach ($routes[$verb] as $pattern => $func) {
		$found = @preg_match('/^' . str_replace('/', '\/', $pattern) . '$/', $url, $matches);
		if ($found) {
			foreach ($matches as $key => $val) {
				$param[$key] = $val;
			}
			return $func($param);
		}
	}
	return false;
}


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
		router_error_400('Route ' . $route . ' does not exist');
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
function router_error_400($reason = '') {
	@header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
	echo $reason;
	die();
}

/**
 *	Output a HTTP error 404
 *
 *	This function does not return.
 *	@param $reason reason for eventual logging (not implemented yet)
 */
function router_error_404($reason = '') {
	@header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
	echo $reason;
	die();
}

/**
 *	Output a HTTP error 500
 *
 *	This function does not return.
 *	@param $reason reason for eventual logging (not implemented yet)
 */
function router_error_500($reason = '') {
	@header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
	echo $reason;
	die();
}
