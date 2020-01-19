#!/usr/bin/php
<?php

require_once(__DIR__.'/SMSOptInHandler.php');
require_once(__DIR__.'/TwilioHandler.php');

$optin = new SMSOptInHandler();
$twilio = new TwilioHandler();
$number = "+17606082578";
$info = $optin->get_optin_info($number);
echo "info:\n";
print_r($info);
$status = $optin->get_optin_status($number);
echo "status='$status'\n";

switch ($status) {
case 'new':
	$code = $optin->generate_confirmation_code($number);
	$twilio->send_confirmation_request($number, $code);
	break;
case 'awaiting_confirmation':
	echo "Enter confirmation code: ";
	$input = trim(fgets(STDIN));
	if ($optin->confirm($number, $input)) {
		echo "Success.\n";
	}
	else
		echo "Failure.\n";
	break;
case 'confirmed':
	echo "The number is already confirmed.\n";
	break;
default:
	echo "Unknown status.\n";
}

?>
