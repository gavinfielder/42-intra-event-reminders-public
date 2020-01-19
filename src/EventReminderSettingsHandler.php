<?php
require_once(__DIR__ . '/../settings.php');
require_once(__DIR__ . '/error.php');
require_once(__DIR__ . '/logging.php');
require_once(__DIR__ . "/event-reminder-settings-database.php");

class EventReminderSettingsHandler implements IEventReminderSettingsHandler {
	
	private $settings_db;

	function __construct($api) {
		$this->settings_db = new EventReminderSettingsDatabase($api);
	}

	public function get_user_settings($user_id) {
		return ($this->settings_db->get_user_settings($user_id));
	}

	public function write_user_settings($user_id, $reminder_times_json, $reminder_platforms_json) {
		return ($this->settings_db->write_settings($user_id, $reminder_times_json, $reminder_platforms_json));
	}
}
