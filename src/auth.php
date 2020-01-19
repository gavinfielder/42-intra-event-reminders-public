<?php
require_once(__DIR__ . '/../settings.php');
require_once(__DIR__."/logging.php");
require_once(__DIR__."/error.php");
require_once(__DIR__.'/Factory.php');

function	authorize() {
	$auth = EventRemindersFactory::Create('IAuthenticationHandler', true);
	$auth->authorize();
}

/* --------------------------------------------------------
 *  Everything below this is legacy code
 * ------------------------------------------------------*/

function	check_token_validity($token) {
	$url = 'https://api.intra.42.fr/oauth/token/info';
	if (is_array($token))
		$token = $token['access_token'];
    $options = array('http' => array(
        'header' => "Authorization: Bearer ".$token."\r\n",
        'method' => 'GET',
    ));
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === false) {
        auth_error_log('API call failed while getting token info');
        return (null);
    }
	$arr = json_decode(trim($result), true);
	if (isset($arr['expires_in_seconds']) && intval($arr['expires_in_seconds']) > 20)
		return (true);
	return (false);
}

function	redirect_legacy($url) {
	ob_start();
	header('Location: '.$url);
	ob_end_flush();
	exit(0);
}

/*
function	auth_error_log($msg) {
	if (php_sapi_name() == 'cli')
		fwrite(STDERR, $msg."\n");
	else
		error_log($msg);
}

$redirect_uri = 'http://localhost:9876';
$client_id = '8ee3e67b189e3e7c0bd99a8e6d46deab6daf8783c6ef639911f2fbceb20475dc';
$client_secret = '86e3ac470797920d52c7bc31be5cdfd532a7babe52b1e1ca81c72b0473d2bc6d';
$authorization_dialog = "https://api.intra.42.fr/oauth/authorize?client_id=$client_id&redirect_uri=$redirect_uri&response_type=code&scope=public&state=authorizing";
//Attempt to authorize and return an array that includes an 'access_token' member
//Returns false if unable to authorize
//This is a dispatch function that routes based on interface
function	legacy_authorize() {
	if (php_sapi_name() == 'cli')
		return (authorize_cli());
	else
		return (authorize_http());
}


//Dispatched from authorize() when the interface is cli
function	authorize_cli() {
	//First check the ideal path: refresh with a valid refresh token
	if (($refresh_token = load_refresh_token())
			&& ($token = refresh_token($refresh_token))
			&& check_token_validity($token))
		return ($token);
	auth_error_log('(cli) could not refresh token, checking existing token');

	//If that failed, load the stored access token and see if it's still valid
	if (($token = load_access_token())
			&& check_token_validity($token))
		return (array('access_token' => $token));
	auth_error_log('(cli) could not load existing token, attempting to generate new token');

	//If that failed, try to get a brand new token
	if (($code = get_access_code())
			&& ($token = get_access_token($code))
			&& check_token_validity($token))
			return ($token);

	//If that failed, exit with failure
	return (false);
}

//Dispatched from authorize() when the interface is not cli
function	authorize_http() {
	//First check the ideal path: refresh with a valid refresh token
	if (isset($_SESSION['refresh_token'])
			&& ($token = refresh_token($_SESSION['refresh_token']))
			&& check_token_validity($token))
		return ($token);
	auth_error_log('could not refresh token, checking existing token');
	//If that failed, load the stored access token and see if it's still valid
	if (isset($_SESSION['access_token'])
			&& check_token_validity($_SESSION['access_token']))
		return (array('access_token' => $_SESSION['access_token']));
	auth_error_log('could not load existing token, attempting to generate new token');
	//If that failed, try to get a brand new token
	if (($code = get_access_code())
			&& ($token = get_access_token($code))
			&& check_token_validity($token))
		return ($token);
	//If that failed, exit with failure
	return (false);
}

function	load_access_token() {
	global $dir_root;
	if (file_exists($dir_root.'/credentials/token'))
		$token = file_get_contents($dir_root.'/credentials/token');
	if (!(isset($token)) || $token === false) {
		auth_error_log('could not load access token');
		return (null);
	}
	return ($token);
}

function	load_refresh_token() {
	global $dir_root;
	if (file_exists($dir_root.'/credentials/refresh_token'))
		$token = file_get_contents($dir_root.'/credentials/refresh_token');
	if (!(isset($token)) || $token === false) {
		auth_error_log('could not load refresh token');
		return (null);
	}
	return ($token);
}

function	save_access_token(array $token) {
	global $dir_root;

	if (!(file_exists($dir_root.'/credentials')))
		shell_exec("mkdir $dir_root/credentials");
	if (!(file_exists($dir_root.'/credentials')))
		return (false);
	$f1 = fopen($dir_root.'/credentials/token', 'w');
	$f2 = fopen($dir_root.'/credentials/refresh_token', 'w');
	if ($f1 && $f2) {
		fwrite($f1, $token['access_token']);
		fwrite($f2, $token['refresh_token']);
		fclose($f1);
		fclose($f2);
	}
	else
		auth_error_log('error saving access token');
}

function	get_access_code() {
	global $authorization_dialog;

	if (isset($_GET['code'])) {
		return ($_GET['code']);
	}
	else if (php_sapi_name() == 'cli') {
		fwrite(STDOUT, "Enter access code: ");
		$code = fgets(STDIN);
		return (trim($code));
	}
	else {
		redirect($authorization_dialog);
	}
}

function	get_access_token($code) {
	global $client_id;
	global $client_secret;
	global $redirect_uri;

	$url = 'https://api.intra.42.fr/oauth/token';
	$query = http_build_query(array(
		'grant_type' => 'authorization_code',
		'client_id' => $client_id,
		'client_secret' => $client_secret,
		'code' => $code,
		'redirect_uri' => $redirect_uri,
		'state' => 'authorizing'
	));
	$options = array(
	    'http' => array(
			'header'  => "Content-type: application/x-www-form-urlencoded\r\n".
						 "Content-Length: ".strlen($query)."\r\n",
	        'method'  => 'POST',
	        'content' => $query
	    )
	);
	$context  = stream_context_create($options);
	$result = file_get_contents($url, false, $context);
	if ($result === FALSE) {
		auth_error_log('could not get access token');
		return (null);
	}
	$arr = json_decode(trim($result), true);
	if (!(isset($arr['access_token']))) {
		auth_error_log('access_token was not found in the token exchange response');
		auth_error_log("Response: $result");
		return (null);
	}
	if (php_sapi_name() != "cli") {
		$_SESSION['access_token'] = $arr['access_token'];
		$_SESSION['refresh_token'] = $arr['refresh_token'];
	}
	else
		save_access_token($arr);
	return ($arr);
}

function	refresh_token($token)
{
	global $client_id;
	global $client_secret;
	global $redirect_uri;

	$refresh_token = load_refresh_token();
	if (!($refresh_token)) {
		auth_error_log('error refreshing token: could not load refresh token');
		return (false);
	}
	$url = 'https://api.intra.42.fr/oauth/token';
	$query = http_build_query(array(
		'grant_type' => 'refresh_token',
		'client_id' => $client_id,
		'client_secret' => $client_secret,
		'refresh_token' => $refresh_token,
		'redirect_uri' => $redirect_uri,
		'state' => 'authorizing'
	));
	$options = array(
	    'http' => array(
			'header'  => "Content-type: application/x-www-form-urlencoded\r\n".
						 "Content-Length: ".strlen($query)."\r\n",
	        'method'  => 'POST',
	        'content' => $query
	    )
	);
	$context  = stream_context_create($options);
	$result = file_get_contents($url, false, $context);
	if ($result === FALSE) {
		auth_error_log('could not get refreshed access token');
		return (null);
	}
	$arr = json_decode(trim($result), true);
	if (!(isset($arr['access_token']))) {
		auth_error_log('access_token was not found in the token exchange response');
		auth_error_log("Response: $result");
		return (null);
	}
	if (php_sapi_name() != "cli") {
		$_SESSION['access_token'] = $arr['access_token'];
		$_SESSION['refresh_token'] = $arr['refresh_token'];
	}
	else
		save_access_token($arr);
	return ($arr);
}

function	redirect($url) {
	ob_start();
	header('Location: '.$url);
	ob_end_flush();
	exit(0);
}
 */

?>
