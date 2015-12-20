<?php

/*
	GitHub integration for Sausage Machine
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

@require_once('config.inc.php');
require_once('git.inc.php');
require_once('makefile.inc.php');
require_once('router.inc.php');
require_once('util.inc.php');
require_once('vendor/OAuth2/Client.php');
require_once('vendor/OAuth2/GrantType/IGrantType.php');
require_once('vendor/OAuth2/GrantType/AuthorizationCode.php');


/**
 *	Get the URL to redirect a client to in order to get a GitHub authentication token
 *	@return string
 */
function github_get_auth($param = array()) {
	$redirect_uri = base_url();
	// add explicit route since GitHub prepends it with their own keys and values
	$redirect_uri .= 'github.php?auth_callback';
	unset($param[0]);
	$redirect_uri .= '&' . http_build_query($param);

	$client = new OAuth2\Client(config('github_client_id'), config('github_client_secret'));
	$auth_url = $client->getAuthenticationUrl('https://github.com/login/oauth/authorize', $redirect_uri, array('scope' => 'public_repo'));
	return $auth_url;
}


/**
 *	Called by GitHub - set the access token as cookie and redirect to URL specified by client
 */
function github_get_auth_callback($param = array()) {
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
function github_post_repo($param = array()) {
	if (empty($param['github_access_token'])) {
		router_error_400('Required parameter github_access_token missing or empty');
	}
	if (empty($param['github_repo_name'])) {
		router_error_400('Required parameter github_repo_name missing or empty');
	}
	if (empty($param['temp'])) {
		router_error_400('Required parameter temp missing or empty');
	}

	$github_repo = github_create_repo($param['github_access_token'], $param['github_repo_name']);
	if ($github_repo === false) {
		router_error_500('Error creating GitHub repository ' . $param['github_repo_name'] . '. Make sure there is no existing repository with the same name.');
	}

	$ret = github_add_collaborator($param['github_access_token'], $github_repo, config('github_push_as'));
	if ($ret === false) {
		router_error_500('Error adding ' . config('github_push_as') . ' as a collaborator to ' . $github_repo);
	}

	$ret = github_add_webhook($param['github_access_token'], $github_repo);
	if ($ret === false) {
		router_error_500('Error adding webhook to ' . $github_repo);
	}

	$modified = repo_get_modified_files($param['temp']);
	$ret = repo_stage_files($param['temp'], $modified);
	if ($ret === false) {
		router_error_500('Error staging files ' . implode(', ', $modified) . ' to ' . $param['temp']);
	}

	$ret = repo_commit($param['temp'], 'Initial commit');
	if ($ret === false) {
		router_error_500('Error committing ' . $param['temp']);
	}

	$ret = repo_push($param['temp'], 'ssh://git@github.com/' . $github_repo . '.git');
	if ($ret === false) {
		router_error_500('Error pushing to ' . $github_repo . '. Make sure all checks in setup.php pass.');
	}

	// add to projects.json
	// XXX: turn into a function, make atomic
	$s = @file_get_contents(rtrim(config('content_dir', 'content'), '/') . '/projects.json');
	$projects = @json_decode($s, true);
	if (!@is_array($projects)) {
		$projects = array();
	}
	$projects[] = array('created' => time(), 'updated' => time(), 'github_repo' => $github_repo, 'parent' => repo_get_url($param['temp']));
	$old_umask = @umask(0000);
	@file_put_contents(rtrim(config('content_dir', 'content'), '/') . '/projects.json', json_encode($projects));
	@umask($old_umask);

	return $github_repo;
}


function github_post_push($param = array()) {
	$payload = json_decode($param['payload'], true);

	// prevent error on "ping" notifications
	if (!isset($payload['head_commit']['message'])) {
		return 'Not a commit';
	}

	// prevent recursions
	if ($payload['head_commit']['message'] === 'Regenerate output files') {
		return 'Not acting on my own changes';
	}

	// ref is like "refs/heads/master"
	$branch = @array_pop(explode('/', $payload['ref']));
	$tmp_key = get_repo($payload['repository']['clone_url'], $branch, true);
	if ($tmp_key === false) {
		router_error_500('Error getting branch ' . $branch . ' of ' . $payload['repository']['clone_url']);
	}

	// XXX: implement make all in template
	// XXX: make html removes book.epub
	make_run(tmp_dir($tmp_key), 'html', $tmp);
	$out = $tmp;
	make_run(tmp_dir($tmp_key), 'book.epub', $tmp);
	$out = array_merge($out, $tmp);
	make_run(tmp_dir($tmp_key), 'icmls', $tmp);
	$out = array_merge($out, $tmp);

	$modified = repo_get_modified_files($tmp_key);
	if (empty($modified)) {
		// nothing to commit
		return 'No changes';
	}

	$ret = repo_stage_files($tmp_key, $modified);
	if ($ret === false) {
		router_error_500('Error staging files ' . implode(', ', $modified) . ' to ' . $tmp_key);
	}

	$ret = repo_commit($tmp_key, 'Regenerate output files');
	if ($ret === false) {
		router_error_500('Error committing ' . $tmp_key);
	}

	$ret = repo_push($tmp_key, $payload['repository']['ssh_url']);
	if ($ret === false) {
		router_error_500('Error pushing to ' . $payload['repository']['ssh_url']);
	}

	// XXX: move
	$s = @file_get_contents(rtrim(config('content_dir', 'content'), '/') . '/projects.json');
	$projects = @json_decode($s, true);
	if (!@is_array($projects)) {
		$projects = array();
	}
	foreach ($projects as &$p) {
		if ($payload['repository']['full_name'] === $p['github_repo']) {
			$p['updated'] = time();
		}
	}
	$old_umask = @umask(0000);
	@file_put_contents(rtrim(config('content_dir', 'content'), '/') . '/projects.json', json_encode($projects));
	@umask($old_umask);

	return 'Success';
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

	// special case: repo owner and collaborator are the same
	$pos = strpos($github_repo, '/');
	if ($pos !== false && substr($github_repo, 0, $pos) === $collaborator) {
		return true;
	}

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
			'url' => base_url() . 'github.php?push',
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



register_route('GET' , 'auth', 'github_get_auth');
/* invoked by GitHub */
register_route('GET' , 'auth_callback=?', 'github_get_auth_callback');
register_route('POST', 'repo', 'github_post_repo');
/* invoked by GitHub webhook */
register_route('POST', 'push=?', 'github_post_push');


$query = $_SERVER['QUERY_STRING'];
// use the first URL argument for the router
$pos = strpos($query, '&');
if ($pos !== false) {
	$query = substr($query, 0, $pos);
}
// return JSON by default
@header('Content-type: application/json; charset=utf-8');
echo json_encode(route($_SERVER['REQUEST_METHOD'], $query));
