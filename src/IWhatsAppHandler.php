<?php

interface IWhatsAppHandler {
	public function send_whatsapp_reminder($user, $event, IEventReminderSettingsHandler $sets);
}

?>
