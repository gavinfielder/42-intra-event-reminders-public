<?php

require_once(__DIR__ . '/master.php');
require_once(__DIR__ . '/Factory.php');

$posted_number = trim($_POST['user-sms']);

if (!empty($posted_number)) { //Don't try to validate an empty field, accept it.
	$same = is_same_number($posted_number);
	
	if (!$same) {
		if (is_valid_number($posted_number)) {
			send_sms_code($posted_number);
		} else
			finish("invalid");
	}
	else
		finish("success");
} else
	finish("success");





function is_same_number($posted_number) {
	$settings = EventRemindersFactory::Create('IEventReminderSettingsHandler', true);
	if ($settings = $settings->get_user_settings($_SESSION['user_id'])) {
		$stored_phone_number = $settings['reminder_platforms_json']['user-sms'];
		return ($stored_phone_number === $posted_number);
	}
	return (false);
}

function is_valid_number($number) {
	$twilio_handler = EventRemindersFactory::Create('ISMSHandler', true);
	return ($twilio_handler->validate_number($number));
}

function finish($message) {
	die($message);
}

function send_sms_code($number) {
	$sms_handler = EventRemindersFactory::Create('ISMSHandler', true);
	$sms_optin_handler = EventRemindersFactory::Create('ISMSOptInHandler', true);

	$_SESSION['valid_sms_code'] = $number;
	$code = $sms_optin_handler->generate_confirmation_code($number);
	$sms_handler->send_confirmation_request($number, $code);
	die("verify");
}
