<?php

require_once(__DIR__ . '/master.php');
require_once(__DIR__ . '/Factory.php');

if (isset($_POST['code']) && !empty($_POST['code'])) {
	$optin_handler = EventRemindersFactory::Create('ISMSOptInHandler', true);
	if ($optin_handler->confirm($_SESSION['valid_sms_code'], $_POST['code'])) {
		unset($_SESSION['valid_sms_code']);
		die("SMS verified.");
	} else
		die(header('HTTP/1.0 400 Bad Request'));
} else
	die(header('HTTP/1.0 400 Bad Request'));
