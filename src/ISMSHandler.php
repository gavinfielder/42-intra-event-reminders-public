<?php

interface ISMSHandler {
	public function send_sms_reminder($user, $event, IEventReminderSettingsHandler $sets);
	public function validate_number($input);
	public function send_confirmation_request($number, $confirmation_code);
}

?>
