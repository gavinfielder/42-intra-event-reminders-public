<?php

require_once(__DIR__.'/../settings.php');
require_once(__DIR__.'/error.php');
require_once(__DIR__.'/logging.php');
require_once(__DIR__.'/../vendor/autoload.php');

class	SlackDataHandler {
	const DB_PATH = __DIR__.'/../databases/';
	private $slack_token;
	private $campus_id;
	private $db_name;
	private $db; //SQLite3 object

	//Private so it can't be instantiated outside of factory method
	private function __construct(int $campus_id) {
		$this->db_name = "slack_data_$campus_id.db";
		$this->campus_id = $campus_id;
		$this->initialize();
	}

	function	__destruct() {
		$this->db->close();
	}

	static function	Create(int $campus_id) {
		switch ($campus_id) {
		case 7: //Fremont Campus
			$sdh = new SlackDataHandler($campus_id);
			$sdh->slack_token = SLACK_FREMONT_TOKEN;
			return ($sdh);
		default:
			cli_error_log("SlackDataHandler: could not instantiate: campus $campus_id not handled.");
			return (false);
		}
	}

	private function initialize() {
		$new_db = false;
		if (!file_exists(self::DB_PATH)) {
			$new_db = true;
			if (!mkdir(self::DB_PATH, 0777, true)) {
				cli_error("SlackDataHandler: Fatal: Unable to create database directory.", true);
			}
		}
		try {
			if (!file_exists((self::DB_PATH).($this->db_name)))
				$new_db = true;
			$path = self::DB_PATH . $this->db_name;
			$this->db = new SQLite3($path);
			chmod($path, 0664);
			chgrp($path, 'www');
		}
		catch (Exception $e) {
				cli_error("SlackDataHandler: Fatal: Unable to open db file '$db_name'.", true);
		}
		if ($this->db && $new_db)
			$this->make_tables();
	}

	private function make_tables() {
		$result = $this->db->query(
			"CREATE TABLE IF NOT EXISTS matched_users(
				user_id INTEGER PRIMARY KEY,
				slack_id TEXT NOT NULL
			);");
		if ($result === false) {
			cli_error_log("SlackDataHandler: could not make matched_users table. Reason: "
				.$this->db->lastErrorMsg());
			return (false);
		}
		$result = $this->db->query(
			"CREATE TABLE IF NOT EXISTS slack_directory(
				slack_id TEXT PRIMARY KEY,
				username TEXT,
				real_name TEXT,
				display_name TEXT,
				first_name TEXT,
				last_name TEXT,
				phone TEXT,
				email TEXT
			);");
		if ($result === false) {
			cli_error_log("SlackDataHandler: could not make slack_directory table. Reason: "
				.$this->db->lastErrorMsg());
			return (false);
		}
		return (true);
	}

	function	update_slack_directory() {
		$client = new wrapi\slack\slack($this->slack_token);
		if (!$client) {
			cli_error_log("Could not update slack directory: slack client failed intialization");
			return (false);
		}
		$arr = $client->users->list(array());
		if (!($arr['ok']) || !isset($arr['members'])) {
			cli_error_log("Could not update slack directory: slack api call failed");
			return (false);
		}
		$arr = $arr['members'];
		foreach ($arr as $i => $entry) {

			//Prepare data
			$slack_id = $entry['id'];
			if (isset($entry['name'])) $slack_username = $entry['name'];
			else $slack_username = null;
			if (isset($entry['real_name'])) $slack_realname = $entry['real_name'];
			else $slack_realname = null;
			if (isset($entry['profile']['display_name'])) $slack_displayname = $entry['profile']['display_name'];
			else $slack_displayname = null;
			if (isset($entry['profile']['first_name'])) $slack_firstname = $entry['profile']['first_name'];
			else $slack_firstname = null;
			if (isset($entry['profile']['last_name'])) $slack_lastname = $entry['profile']['last_name'];
			else $slack_lastname = null;
			if (isset($entry['profile']['phone'])) $slack_phone = $entry['profile']['phone'];
			else $slack_phone = null;
			if (isset($entry['profile']['email'])) $slack_email = $entry['profile']['email'];
			else $slack_email = null;

			//Delete record
			$result = $this->db->query("DELETE FROM slack_directory WHERE slack_id = '$slack_id';");
			if ($result === false) {
				cli_error_log("Could not update slack directory entry: could not delete. Reason: "
					.$this->db->lastErrorMsg());
			}

			//Insert new record
			$query = $this->db->prepare("INSERT INTO slack_directory
				(slack_id, username, real_name, display_name, first_name, last_name, phone, email)
				VALUES (:this_id, :this_username, :this_realname, :this_displayname,
						:this_firstname, :this_lastname, :this_phone, :this_email);");
			$query->bindValue(':this_id', $slack_id);
			$query->bindValue(':this_username', $slack_username);
			$query->bindValue(':this_realname', $slack_realname);
			$query->bindValue(':this_displayname', $slack_displayname);
			$query->bindValue(':this_firstname', $slack_firstname);
			$query->bindValue(':this_lastname', $slack_lastname);
			$query->bindValue(':this_phone', $slack_phone);
			$query->bindValue(':this_email', $slack_email);
			$result = $query->execute();
			if ($result === false) {
				cli_error_log('SlackDataHandler: Could not insert slack directory entry: Reason: '.
					$this->db->lastErrorMsg());
			}
			if (DEBUG)
				cli_log("SlackDataHandler: successfully updated user $slack_id ($slack_username)");
		}
		return (true);
	}

	/**
	 * Get associated slack_id of the given user_id
	 *
	 * Return slack_id, or null on not found, or false on error
	 */
	function	get_slack_id($user_id) {
		$result = $this->db->query("SELECT slack_id FROM matched_users WHERE user_id = $user_id");
		if (!$result) {
			cli_error_log("SlackDataHandler: get_slack_id: db query failed. Reason: "
				.$this->db->lastErrorMsg());
			return (false);
		}
		$arr = $result->fetchArray(SQLITE3_ASSOC);
		if ($arr === false) {
			return (null);
		}
		return ($arr['slack_id']);
	}

	/**
	 * Attempt to associate a user with a slack account based on the
	 * input they give on the settings page, or their login if that does not work
	 *
	 * If successfully identified, return the slack directory entry
	 * If not, return false
	 */
	function	identify_user($slack_input, $user) {
		if (true === ($reg = $this->attempt_association_on_condition(
				$slack_input, $user, "display_name = '$slack_input'", $result)))
			return ($result);
		if (true === ($ret = $this->attempt_association_on_condition(
				$slack_input, $user, "username = '$slack_input'", $result)))
			return ($result);
		if (true === ($ret = $this->attempt_association_on_condition(
				$slack_input, $user, "real_name = '$slack_input'", $result)))
			return ($result);
		if (true === ($ret = $this->attempt_association_on_condition(
				$slack_input, $user, "display_name = '".$user['login']."'", $result)))
			return ($result);
		if (true === ($ret = $this->attempt_association_on_condition(
				$slack_input, $user, "username = '".$user['login']."'", $result)))
			return ($result);
		if (true === ($ret = $this->attempt_association_on_condition(
				$slack_input, $user, "real_name = '".$user['login']."'", $result)))
			return ($result);
		return (false);
	}

	/**
	 * Helper function for identify_user
	 * See if the condition matches. If so, associate the user with the slack id
	 */
	private function attempt_association_on_condition(
						$slack_input, $user, $condition, &$result) {
		if (is_array($result = $this->identify_try($condition))) {
			$this->associate($user['user_id'], $result['slack_id']);
			return (true);
		}
		return (false);
	}

	/**
	 * Helper function for identify_user
	 * Set association between a user_id and a slack_id
	 * as a record in the matched_users table
	 */
	private function associate($user_id, $slack_id) {
		$result = $this->db->query("DELETE FROM matched_users WHERE slack_id = '$slack_id';");
		if ($result === false)
			cli_error_log("SlackDataHandler: associating $user_id with $slack_id: DELETE failed.");
		$result = $this->db->query("INSERT INTO matched_users (user_id, slack_id)
			VALUES ($user_id, '$slack_id');");
		if ($result === false) {
			cli_error_log("SlackDataHandler: associating $user_id with $slack_id: DELETE failed.");
			return (false);
		}
		return (true);
	}

	/**
	 * Helper function for identify_user
	 * Attempt to identify the user witht the given condition
	 *
	 * Return the result directory entry if success (exactly 1 record found)
	 * Return -1 if query fails
	 * Return -2 if no results
	 * Return -3 if multiple results
	 * Return -4 if none of the above or other error
	 */
	private function identify_try($condition) {
		$result = $this->db->query("SELECT * FROM slack_directory WHERE $condition");
		if ($result === false) {
			cli_error_log("SlackDataHandler::identify_try: db query failed on condition \"$condition\"");
			return (-1);
		}
		$result1 = $result->fetchArray(SQLITE3_ASSOC);
		$result2 = $result->fetchArray(SQLITE3_ASSOC);
		if (!$result1)
			return (-2);
		if ($result1 && $result2 === false)
			return ($result1);
		if ($result2)
			return (-3);
		return (-4);
	}

}
