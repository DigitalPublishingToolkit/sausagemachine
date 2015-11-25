<?php

@require_once('config.inc.php');
require_once('git.inc.php');
require_once('makefile.inc.php');
require_once('vendor/OAuth2/Client.php');
require_once('vendor/OAuth2/GrantType/IGrantType.php');
require_once('vendor/OAuth2/GrantType/AuthorizationCode.php');

// XXX: move
function base_url() {
	return 'http://sukzessiv.net/newage2/index.php';
}

/**
 *	Get the URL to redirect a client to in order to get a GitHub authentication token
 *	@return string
 */
function route_get_github_auth($param = array()) {
	$redirect_uri = base_url();
	// add explicit route since GitHub prepends it with their own keys and values
	$redirect_uri .= '?route=github_auth_callback';
	// add custom parameter
	$redirect_uri .= '&' . http_build_query($param);

	$client = new OAuth2\Client(config('github_client_id'), config('github_client_secret'));
	$auth_url = $client->getAuthenticationUrl('https://github.com/login/oauth/authorize', $redirect_uri, array('scope' => 'repo'));
	return $auth_url;
}

/**
 *	Called by GitHub - set the access token as cookie and redirect to URL specified by client
 */
function route_get_github_auth_callback($param = array()) {
	if (empty($param['code'])) {
		// Required parameter code missing or empty
		// see https://developer.github.com/v3/oauth/#web-application-flow
		setcookie('github_access_token', NULL, -1);
		@header('Location: ' . (!empty($param['target']) ? $param['target'] : base_url()));
		die();
	}

	// ask GitHub for access token
	$client = new OAuth2\Client(config('github_client_id'), config('github_client_secret'));
	$response = $client->getAccessToken('https://github.com/login/oauth/access_token', 'authorization_code', array('code' => $param['code'], 'redirect_uri' => ''));

	// parse response
	parse_str($response['result'], $oauth_param);
	if (empty($oauth_param['access_token'])) {
		// Expected parameter access_token missing or empty
		// see print_r($response, true)
		setcookie('github_access_token', NULL, -1);
	} else {
		setcookie('github_access_token', $oauth_param['access_token']);
	}

	@header('Location: ' . (!empty($param['target']) ? $param['target'] : base_url()));
	die();
}

/**
 *	Create a repository on GitHub and push a local (temporary) repository to it
 */
function route_post_github_repo($param = array()) {
	if (empty($param['github_access_token'])) {
		router_bad_request('Required parameter github_access_token missing or empty');
	}
	if (empty($param['github_repo_name'])) {
		router_bad_request('Required parameter github_repo_name missing or empty');
	}
	if (empty($param['tmp_key'])) {
		router_bad_request('Required parameter tmp_key missing or empty');
	}

	$github_repo = github_create_repo($param['github_access_token'], $param['github_repo_name']);
	if ($github_repo === false) {
		router_internal_server_error('Error creating GitHub repository ' . $param['github_repo_name']);
	}

	$ret = github_add_collaborator($param['github_access_token'], $github_repo, config('github_push_as'));
	if ($ret === false) {
		router_internal_server_error('Error adding ' . config('github_push_as') . ' as a collaborator to ' . $github_repo);
	}

	$ret = github_add_webhook($param['github_access_token'], $github_repo);
	if ($ret === false) {
		router_internal_server_error('Error adding webhook to ' . $github_repo);
	}

	$modified = repo_get_modified_files($param['tmp_key']);
	$ret = repo_stage_files($param['tmp_key'], $modified);
	if ($ret === false) {
		router_internal_server_error('Error staging files ' . implode(', ', $modified) . ' to ' . $param['tmp_key']);
	}

	$ret = repo_commit($param['tmp_key'], 'Initial commit');
	if ($ret === false) {
		router_internal_server_error('Error committing ' . $param['tmp_key']);
	}

	$ret = repo_push($param['tmp_key'], 'ssh://git@github.com/' . $github_repo . '.git');
	if ($ret === false) {
		router_internal_server_error('Error pushing to ' . $github_repo);
	}

	return $github_repo;
}

function route_post_github_push($param = array()) {
	$payload = json_decode($param['payload'], true);

	// prevent error on "ping" notifications
	if (!isset($payload['head_commit']['message'])) {
		return true;
	}

	// prevent recursions
	if ($payload['head_commit']['message'] === 'Regenerate output files') {
		return true;
	}

	// ref is like "refs/heads/master"
	$branch = @array_pop(explode('/', $payload['ref']));
	$tmp_key = get_repo($payload['repository']['clone_url'], $branch, true);
	if ($tmp_key === false) {
		router_internal_server_error('Error getting branch ' . $branch . ' of ' . $payload['repository']['clone_url']);
	}

	// XXX: implement make all in template
	// XXX: make html removes book.epub
	make_run(tmp_dir($tmp_key), 'html', $tmp);
	$out = $tmp;
	make_run(tmp_dir($tmp_key), 'book.epub', $tmp);
	$out .= "\n\n" . $tmp;
	make_run(tmp_dir($tmp_key), 'icmls', $tmp);
	$out .= "\n\n" . $tmp;

	$modified = repo_get_modified_files($tmp_key);
	if (empty($modified)) {
		// nothing to commit
		return true;
	}

	$ret = repo_stage_files($tmp_key, $modified);
	if ($ret === false) {
		router_internal_server_error('Error staging files ' . implode(', ', $modified) . ' to ' . $tmp_key);
	}

	$ret = repo_commit($tmp_key, 'Regenerate output files');
	if ($ret === false) {
		router_internal_server_error('Error committing ' . $tmp_key);
	}

	$ret = repo_push($tmp_key, $payload['repository']['ssh_url']);
	if ($ret === false) {
		router_internal_server_error('Error pushing to ' . $payload['repository']['ssh_url']);
	}

	return true;
}

/**
 *	Create a repository on GitHub
 *	@param String $github_access_token see route_get_github_auth & route_get_github_auth_callback
 *	@param String $github_repo_name name of repository to create
 *	@return String GitHub username, followed by a slash, followed by the name of the respository
 */
function github_create_repo($github_access_token, $github_repo_name) {
	$client = new OAuth2\Client(config('github_client_id'), config('github_client_secret'));
	$client->setAccessToken($github_access_token);
	$client->setAccessTokenType(OAuth2\Client::ACCESS_TOKEN_TOKEN);
	$client->setCurlOption(CURLOPT_USERAGENT, config('github_useragent'));

	$response = $client->fetch('https://api.github.com/user/repos', json_encode(array('name' => $github_repo_name)), 'POST');
	if (!@is_string($response['result']['full_name'])) {
		return false;
	} else {
		return $response['result']['full_name'];
	}
}

/**
 *	Add a collaborator to a repository on GitHub
 *	@param String $github_access_token see route_get_github_auth & route_get_github_auth_callback
 *	@param String $github_repo GitHub username, followed by a slash, followed by the name of the respository
 *	@param String $collaborator GitHub username to add
 *	@return true if sucessful, false if not
 */
function github_add_collaborator($github_access_token, $github_repo, $collaborator) {
	$client = new OAuth2\Client(config('github_client_id'), config('github_client_secret'));
	$client->setAccessToken($github_access_token);
	$client->setAccessTokenType(OAuth2\Client::ACCESS_TOKEN_TOKEN);
	$client->setCurlOption(CURLOPT_USERAGENT, config('github_useragent'));

	$response = $client->fetch('https://api.github.com/repos/' . $github_repo . '/collaborators/' . $collaborator, '', 'PUT');
	if (!isset($response['code']) || $response['code'] !== 204) {
		return false;
	} else {
		return true;
	}
}

/**
 *	Add the necessary webhooks for the Sausage Machine to function
 *	@param String $github_access_token see route_get_github_auth & route_get_github_auth_callback
 *	@param String $github_repo GitHub username, followed by a slash, followed by the name of the respository
 *	@return true if sucessful, false if not
 */
function github_add_webhook($github_access_token, $github_repo) {
	$client = new OAuth2\Client(config('github_client_id'), config('github_client_secret'));
	$client->setAccessToken($github_access_token);
	$client->setAccessTokenType(OAuth2\Client::ACCESS_TOKEN_TOKEN);
	$client->setCurlOption(CURLOPT_USERAGENT, config('github_useragent'));

	$param = array(
		'name' => 'web',
		'active' => true,
		'events' => array(
			'push'
		),
		'config' => array(
			// XXX: change
			'url' => base_url() . '?github_push',
			'content_type' => 'form'
		)
	);

	$response = $client->fetch('https://api.github.com/repos/' . $github_repo . '/hooks', json_encode($param), 'POST');
	if (!isset($response['code']) || $response['code'] !== 201) {
		return false;
	} else {
		return true;
	}
}
