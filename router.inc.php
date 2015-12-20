<?php

/*
	Simple router for Sausage Machine
	Copyright (C) 2015  Gottfried Haider for PublishingLab

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

$routes = array();


/**
 *	Register a possible route
 *	@param $verb e.g. 'GET', 'POST'
 *	@param $pattern regex, first match is executed
 *	@param $func function to call
 */
function register_route($verb, $pattern, $func) {
	global $routes;
	$verb = strtoupper($verb);

	if (!@is_array($routes[$verb])) {
		$routes[$verb] = array();
	}

	$routes[$verb][$pattern] = $func;
}


/**
 *	Route client request
 *
 *	If the route does not exist, the function will send a HTTP error
 *	400 and stop execution. This function adds all all arguments in
 *	$_REQUEST to the params array, as well as the regex matches, as
 *	$param[0], $param[1] etc.
 *	@param $verb e.g. $_SERVER['REQUEST_METHOD']
 *	@param $url e.g. first argument in $_SERVER['QUERY_STRING']
 *	@param $param additional arguments to add
 *	@return return value from function being invoked
 */
function route($verb, $url, $param = array()) {
	global $routes;
	$verb = strtoupper($verb);

	if (!@is_array($routes[$verb])) {
		return false;
	}

	foreach ($_REQUEST as $key => $val) {
		// the first parameter will end up as $param[0], $param[1], etc anyway, so ignore it here
		if ($key === array_keys($_REQUEST)[0] && empty($val)) {
			continue;
		}
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

	// nothing matched, return 400
	router_error_400('Route ' . $verb . ' ' . $url . ' does not exist');
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
