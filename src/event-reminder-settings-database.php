<?php
require_once(__DIR__ . '/../settings.php');
require_once(__DIR__."/logging.php");
require_once(__DIR__."/error.php");
require_once(__DIR__ . '/DatabaseManager.php');

class EventReminderSettingsDatabase extends SQLite3 {

	const DB_PATH = __DIR__ . "/../databases/";
	const DB_FILE = "event-reminder-settings.db";

	private $api;

	function __construct($api) {
		if ($new_db = !file_exists(self::DB_PATH)) {
			if (!mkdir(self::DB_PATH, 0777, true))
				die("Unable to create directory for database.");
		} else 
			$new_db = !file_exists(self::DB_PATH . self::DB_FILE);
		open_database($this, self::DB_PATH . self::DB_FILE);
		if ($new_db)
			$this->make_table();
		$this->api = $api;
	}

	function make_table() {
		$result = $this->query(
			"CREATE TABLE IF NOT EXISTS event_reminder_settings(
				user_id INTEGER PRIMARY KEY,
				reminder_times_json BLOB,
				reminder_platforms_json BLOB
			 );"
		);
        if ($result === false) {
            cli_error_log("EventReminderSettingsDatabase: Could not make table. Reason: "
                .$this->lastErrorMsg());
        }
	}

	/**
	 * Delete a user to allow for simple insertion of new form data,
	 * if the user_id doesn't exist in the table then nothing happens.
	 */
	function delete_user($user_id) {
		$del = $this->prepare("DELETE FROM event_reminder_settings WHERE user_id=:user_id;");
		$del->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
		$del->execute();
	}

	/**
	 * Writes the user's settings to the database.
	 */
	function write_settings($user_id, $reminder_times_json, $reminder_platforms_json, $delete_first = true) {
		if ($delete_first)
			$this->delete_user($user_id);
		$ins = $this->prepare("INSERT INTO event_reminder_settings(user_id, reminder_times_json, reminder_platforms_json) VALUES (:user_id, :reminder_times_json, :reminder_platforms_json);");
		$ins->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
		$ins->bindValue(':reminder_times_json', $reminder_times_json, SQLITE3_BLOB);
		$ins->bindValue(':reminder_platforms_json', $reminder_platforms_json, SQLITE3_BLOB);
		return ($ins->execute());
	}

	/**
	 * Attempts to read a user's reminder settings, if none are set then defaults are returned and written to the database.
	 */
	function		get_user_settings($user_id) {
		$settings_ps = $this->prepare("SELECT reminder_times_json, reminder_platforms_json FROM event_reminder_settings WHERE user_id=:user_id;");
		$settings_ps->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
		$settings = $settings_ps->execute();
		$settings = $settings->fetchArray(SQLITE3_ASSOC);
		if (!$settings) { //No row? Insert and return defaults.
			$user = $this->api->access_user($user_id);
			$email = (isset($user['email']) ? $user['email'] : '');
			$settings = [
				"reminder_platforms_json" => [
					"user-email" => $email,
				],
				"reminder_times_json" => [
					"reminder-time-1-platform" => "email",
					"reminder-time-1-amount" => 24,
					"reminder-time-1-unit" => "hours",
					"reminder-time-2-platform" => "email",
					"reminder-time-2-amount" => 12,
					"reminder-time-2-unit" => "hours",
					"reminder-time-3-platform" => "email",
					"reminder-time-3-amount" => 1,
					"reminder-time-3-unit" => "hours",
				],
			];
			$this->write_settings(json_encode($settings['reminder_times_json']), json_encode($settings['reminder_platforms_json']), false);
		}
		if (!is_array($settings['reminder_platforms_json']))
			$settings['reminder_platforms_json'] = json_decode($settings['reminder_platforms_json'], true);
		if (!is_array($settings['reminder_times_json']))
			$settings['reminder_times_json'] = json_decode($settings['reminder_times_json'], true);
		return ($settings);
	}
}
