<?php

require_once(__DIR__ . '/../src/FtApiHandler.php');
require_once(__DIR__ . '/../src/FakeFtApiHandler.php');

function	whitelist_user($login) {
	$path = __DIR__.'/../databases/extended_whitelist.txt';
	if (file_exists($path))
		$whitelist = file_get_contents($path);
	else
		$whitelist = "";
	if (strpos($whitelist, $login) === false) {
		$whitelist .= "$login, ";
		file_put_contents($path, $whitelist);
	}
}

session_start();

$path_event_users = __DIR__.'/../databases/fake_event_users.txt';
$path_users = __DIR__.'/../databases/fake_users.txt';
$path_user_events = __DIR__.'/../databases/fake_user_events.txt';
$path_events = __DIR__.'/../databases/fake_events.txt';

if (isset($_SESSION['access_token']) && isset($_GET['select_event'])) {
	$api = new FtApiHandler($_SESSION['access_token']);
	$me = $api->access_me();
	if (isset($me['login'])) {
		$login = $me['login'];
		whitelist_user($login);
		//write to event_users
		if (file_exists($path_event_users))
			$event_users = unserialize(file_get_contents($path_event_users));
		else
			$event_users = array();
		if (!array_key_exists($_GET['select_event'], $event_users)) {
			$event_users[$_GET['select_event']] = array();
		}
		if (!array_key_exists($me['user_id'], $event_users[$_GET['select_event']])) {
			$event_users[$_GET['select_event']][$me['user_id']] = $me;
			file_put_contents($path_event_users, serialize($event_users));
		}
		else echo("Could not register user (40)");
		//write to user_events
		$events = unserialize(file_get_contents($path_events));
		$event = $events[$_GET['select_event']];
		if (file_exists($path_user_events))
			$user_events = unserialize(file_get_contents($path_user_events));
		else
			$user_events = array();
		if (!array_key_exists($me['user_id'], $user_events)) {
			$user_events[$me['user_id']] = array();
		}
		if (!array_key_exists($_GET['select_event'], $user_events[$me['user_id']])) {
			$user_events[$me['user_id']][$_GET['select_event']] = $event;
			file_put_contents($path_user_events, serialize($user_events));
		}
		else echo("Could not register user (55)");
		if (file_exists($path_users))
			$users = unserialize(file_get_contents($path_users));
		else
			$users = array();
		if (!array_key_exists($me['user_id'], $users)) {
			$users[$me['user_id']] = $me;
			file_put_contents($path_users, serialize($users));
		}
		else echo("Could not register user (67)");
	}
	else
		echo("Could not access user identity");
}
else echo("Could not register user (72)");

ob_start();
header('Location: /test-register.php?action_taken=registered');
ob_end_flush();
exit(0);
