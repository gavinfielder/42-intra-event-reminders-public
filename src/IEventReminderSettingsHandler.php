<?php

interface	IEventReminderSettingsHandler {

	public function get_user_settings($user_id);
	public function write_user_settings($user_id, $reminder_times_json, $reminder_platforms_json);

}

?>
