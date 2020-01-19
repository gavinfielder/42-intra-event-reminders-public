<?php

interface ISlackHandler {
	public function send_slack_reminder($user, $event, IEventReminderSettingsHandler $sets);
	public function update_slack_data();
	public function get_slack_id($user_id);
	public function identify_user($query_for, $user_array);
}

?>
