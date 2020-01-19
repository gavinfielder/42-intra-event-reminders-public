<?php
require_once(__DIR__ . '/master.php');
require_once(__DIR__ . '/Factory.php');
require_once(__DIR__ . '/available-platforms.php');

if (isset($_SESSION['user_id']) && isset($_SESSION['campus_id'])) {
	$user_id = $_SESSION['user_id'];
	$campus_id = $_SESSION['campus_id'];
	$settings = EventRemindersFactory::Create('IEventReminderSettingsHandler', true);
	$user_settings = $settings->get_user_settings($user_id);
	//http_error_log(var_export($user_settings, true));
	if (!$user_settings)
		die(header("HTTP/1.0 503 Service Unavailable"));
	if (!check_platform_status('slack', $campus_id))
		$user_settings['reminder_platforms_json']['user-slack'] = "unavailable";
	if (!check_platform_status('sms', $campus_id))
		$user_settings['reminder_platforms_json']['user-sms'] = "unavailable";
	if (!check_platform_status('whatsapp', $campus_id))
		$user_settings['reminder_platforms_json']['user-whatsapp'] = "unavailable";
	echo (json_encode($user_settings));
} else
	die(header("HTTP/1.0 400 Bad Request"));
