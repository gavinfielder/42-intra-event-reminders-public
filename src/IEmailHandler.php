<?php

require_once(__DIR__.'/IEventReminderSettingsHandler.php');

interface IEmailHandler {
	public function email_event_reminder($user, $event, IEventReminderSettingsHandler $sets);
}

?>
