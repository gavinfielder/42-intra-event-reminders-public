<?php

function	whitelist_user($login) {
	$path = __DIR__.'/../databases/extended_whitelist.txt';
	if (file_exists($path))
		$whitelist = file_get_contents($path);
	else
		$whitelist = "";
	if (strpos($whitelist, $login) !== false) {
		$whitelist .= "$login, ";
		file_put_contents($path, $whitelist);
	}
}

function	add_user($user) {
	$path = __DIR__.'/../databases/fake_users.txt';
	if (file_exists($path))
		$users = unserialize(file_get_contents($path));
	else
		$users = array();
	if (!array_key_exists($user['user_id'], $users)) {
		$users[$user['user_id']] = $user;
		file_put_contents($path, serialize($users));
	}
}

session_start();

if (isset($_SESSION['access_token'])) {
	$api = new FtApiHandler($_SESSION['access_token']);
	$me = $api->access_me();
	if (isset($me['login'])) {
		$login = $me['login'];
		whitelist_user($login);
		add_user($me);
	}
}

ob_start();
header('Location: index.php?action_taken=whitelisted');
ob_end_flush();
exit(0);

?>
