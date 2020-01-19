<?php

require_once(__DIR__ . '/master.php');
require_once(__DIR__ . '/Factory.php');

if (isset($_SESSION['user_id']) && isset($_POST['reminder_times_json']) && isset($_POST['reminder_platforms_json'])) {
	$user_id = $_SESSION['user_id'];
	$reminder_times_json = $_POST['reminder_times_json'];
	$reminder_platforms_json = $_POST['reminder_platforms_json'];

	validate_times($reminder_times_json);
	validate_platforms($reminder_platforms_json);

	if (is_reminders_disabled(json_decode($reminder_platforms_json, true)))
		$reminder_platforms_json = '';

	if (!($settings_handler = EventRemindersFactory::Create('IEventReminderSettingsHandler', true)))
		die(header("HTTP/1.0 503 ervice Unavailable"));
	if ($settings_handler->write_user_settings($user_id, $reminder_times_json, $reminder_platforms_json))
		success($user_id, empty($reminder_platforms_json) || is_time_platforms_disabled(json_decode($reminder_times_json, true)));
	else
		die(header("HTTP/1.0 503 Service Unavailable"));
} else
	die(header("HTTP/1.0 400 Bad Request"));

function is_time_platforms_disabled($times) {
	for ($i = 1; $i <= 3; $i++)
		if ($times['reminder-time-' . $i . '-platform'] != 'disable')
			return (false);
	return (true);
}

function success($user_id, $disabled = false) {
	$nsh = EventRemindersFactory::Create('INotificationScheduleHandler', true);
	$nsh->clear_scheduled_reminders_for_user($user_id);
	if ($disabled)
		die("Reminders have been disabled");
	else {
		$nsh->schedule_notifications_all_user_events($user_id);
		die("Settings saved.");
	}
}

function validate_times($times) {
	$times = json_decode($times, true);
	for ($i = 1; $i <= 3; $i++) {
		$value = $times["reminder-time-$i-amount"];
		if (!is_numeric($value) || preg_match('/\./', $value) || intval($value) < 0)
			if ($times["reminder-time-$i-platform"] != 'disable')
				die(header("HTTP/1.0 400 Invalid Amount $i"));
	}
}

function is_reminders_disabled($platforms) {
	return (empty($platforms['user-email']) && empty($platforms['user-sms']) && empty($platforms['user-slack']));
}

function validate_platforms($platforms) {
	$platforms = json_decode($platforms, true);
	if (!empty($platforms['user-email']))
		if (!validate_email_regex($platforms['user-email']))
			die(header("HTTP/1.0 400 Invalid Email"));
	//SMS is handled separately, slack is safe to go unvalidated since the field is more like a general query string.
	//TODO whatsapp validation when it's added.
}

function validate_email_regex($email) {
	return (preg_match('/^(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))$/iD', $email));
}
