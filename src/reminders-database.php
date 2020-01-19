<?php

require_once(__DIR__ . '/../settings.php');
require_once(__DIR__."/logging.php");
require_once(__DIR__."/error.php");
require_once(__DIR__ . '/DatabaseManager.php');

class ReminderDatabase extends SQLite3 {
	const DB_PATH = __DIR__ . "/../databases/";
	const DB_FILE = "reminders.db";
	const TABLE_NAME = "scheduled_reminders";

	function __construct() {
		if ($new_db = !file_exists(self::DB_PATH)) {
			if (!mkdir(self::DB_PATH, 0777, true))
				die("Unable to create directory for database.");
		} else 
			$new_db = !file_exists(self::DB_PATH . self::DB_FILE);
		open_database($this, self::DB_PATH . self::DB_FILE);
		if ($new_db)
			$this->make_table();
	}

	function make_table() {
		$this->query(
			"CREATE TABLE IF NOT EXISTS scheduled_reminders(
				id INTEGER PRIMARY KEY,
				user_id INTEGER,
				campus_id INTEGER,
				event_id INTEGER,
				reminder_id INTEGER,
				scheduled_time INTEGER
			);"
		);
	}

	/**
	 * Deletes a scheduled reminder for the specified user_id and event_id.
	 */
	function delete_reminder($user_id, $event_id, $reminder_id) {
		$del = $this->prepare("DELETE FROM scheduled_reminders WHERE user_id=:user_id AND event_id=:event_id AND reminder_id=:reminder_id;");
		$del->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
		$del->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
		$del->bindValue(':reminder_id', $reminder_id, SQLITE3_INTEGER);
		$del->execute();
	}

	/**
	 * Clears all entries from the scheduled_reminders table.
	 */
	function clear_table() {
		$this->query("DELETE FROM scheduled_reminders;");
	}

	/**
	 * Stores reminder data for the specified user and event id.
	 */
	function schedule_reminder($user_id, $campus_id, $event_id, $reminder_id, $scheduled_time) {
		$ins = $this->prepare("INSERT INTO scheduled_reminders(user_id, campus_id, event_id, reminder_id, scheduled_time) VALUES (:user_id, :campus_id, :event_id, :reminder_id, :scheduled_time);");
		$ins->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
		$ins->bindValue(':campus_id', $campus_id, SQLITE3_INTEGER);
		$ins->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
		$ins->bindValue(':reminder_id', $reminder_id, SQLITE3_INTEGER);
		$ins->bindValue(':scheduled_time', $scheduled_time, SQLITE3_INTEGER);
		$ins->execute();
	}

	/**
	 * Checks whether a certain reminder is scheduled depending on the passed variables.
	 */
	function reminder_exists($user_id, $event_id, $reminder_id) {
		$query = $this->prepare("SELECT id FROM scheduled_reminders WHERE user_id=:user_id AND event_id=:event_id AND reminder_id=:reminder_id;");
		$query->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
		$query->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
		$query->bindValue(':reminder_id', $reminder_id, SQLITE3_INTEGER);
		$res = $query->execute();
		return (!empty($res->fetchArray(SQLITE3_NUM)));
	}
}
