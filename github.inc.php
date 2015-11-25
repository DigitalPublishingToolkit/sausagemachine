<?php

@require_once('config.inc.php');
require_once('vendor/OAuth2/Client.php');
require_once('vendor/OAuth2/GrantType/IGrantType.php');
require_once('vendor/OAuth2/GrantType/AuthorizationCode.php');

/**
 *	Called by the frontend to get an authentification token
 *	Returns the URL to navigate to
 */
function route_get_github_auth($param = array()) {
	$redirect_uri = $_SERVER['HTTP_REFERER'];
	// remove previous route
	$pos = strpos($redirect_uri, '?');
	if ($pos !== false) {
		$redirect_uri = substr($redirect_uri, 0, $pos);
	}
	// add ours
	$redirect_uri .= '?route=github_auth_callback';
	foreach ($param as $key => $val) {
		$redirect_uri .= '&' . $key . '=' . $val;
	}

	$client = new OAuth2\Client(config('github_client_id'), config('github_client_secret'));
	$auth_url = $client->getAuthenticationUrl('https://github.com/login/oauth/authorize', $redirect_uri, array('scope' => 'repo'));
	return $auth_url;
}

/**
 *	Called by GitHub after the client navigates to the URL returned by route_get_github_auth
 *	Invokes another controller specified by target & target_method parameters
 */
function route_get_github_auth_callback($param = array()) {
	if (empty($param['code'])) {
		router_bad_request('The expected parameter "code" does not exist or is empty');
		// see https://developer.github.com/v3/oauth/#web-application-flow
	}

	// get OAuth access token
	$client = new OAuth2\Client(config('github_client_id'), config('github_client_secret'));
	$response = $client->getAccessToken('https://github.com/login/oauth/access_token', 'authorization_code', array('code' => $param['code'], 'redirect_uri' => ''));

	// parse OAuth response
	if (!@is_int($response['code']) || $response['code'] !== 200 || empty($response['result'])) {
		router_bad_request('GitHub returned an unexpected access_token response: ' . print_r($response, true));
	}
	$tmp = explode('&', $response['result']);
	$oauth_param = array();
	foreach ($tmp as $part) {
		$single_param = explode('=', $part);
		if (1 < count($single_param)) {
			$oauth_param[$single_param[0]] = implode('=', array_slice($single_param, 1));
		} else {
			$oauth_param[$single_param[0]] = $single_param[0];
		}
	}
	if (empty($oauth_param['access_token'])) {
		router_bad_request('The expected parameter "access_token" does not exist or is empty');
	}

	// cleanup $_REQUEST for next controller
	unset($_REQUEST['route']);
	unset($_REQUEST['code']);
	if (!empty($param['target'])) {
		$desired_route = $param['target'];
		unset($_REQUEST['target']);
	} else {
		$desired_route = config('default_route', '');
	}
	if (!empty($param['target_method'])) {
		$method = $param['target_method'];
		unset($_REQUEST['target_method']);
	} else {
		$method = 'GET';
	}
	$_REQUEST['github_access_token'] = $oauth_param['access_token'];

	// invoke next controller
	return router($desired_route, $method);
}

function route_get_test($param = array()) {
	return print_r($param, true);
}
