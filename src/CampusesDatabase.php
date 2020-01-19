<?php

require_once(__DIR__.'/../settings.php');
require_once(__DIR__."/error.php");
require_once(__DIR__.'/logging.php');
require_once(__DIR__."/ICampusesDataHandler.php");
require_once(__DIR__ . '/DatabaseManager.php');

/**
 * This file holds the CampusesDatabase handler class
 */

/**
 * CampusesDatabase handles the campus data. It keeps a working copy
 * of the campus table in working memory to improve processing speed
 * while working with the data.
 *
 *   Methods:
 *     access_campuses()            returns the campus information
 *     update_campuses()            updates the campus information
 *
 *   Unless otherwise specified, all methods return true on success
 *   and false on failure.
 *
 *
 * Example usage run.php:
     //Access campus data
     require_once(__DIR__.'/src/CampusesDatabase.php');
     echo "creating database handler...\n";
     $db = new CampusesDatabase();
     echo "accessing campuses...\n";
     $campuses = $db->access_campuses();
	 echo "got ".count($campuses)." campuses.\n";

	 //Update campus data (needs api token)
     require_once(__DIR__.'/src/auth.php');
     $token = authorize();
     echo "updating campuses...\n";
     $db->update_campuses($token);
     $campuses = $db->access_campuses();
	 echo "got ".count($campuses)." campuses.\n";

     print_r($campuses);
 *
 */
class	CampusesDatabase extends SQLite3 implements ICampusesDataHandler {
	const DB_PATH = __DIR__ . "/../databases/";
	const DB_FILE = "campuses.db";

	private $campuses;

	/**
	 * Constructor
	 */
	function __construct() {
		$this->campuses = null;
		$this->initialize();
	}

	/**
	 * Destructor
	 */
	function __destruct() {
		$this->close();
	}

	/**
	 * Opens or creates database file if it doesn't exist,
	 */
	private function initialize() {
		$new_db = false;
		if (!file_exists(self::DB_PATH)) {
			$new_db = true;
			if (!mkdir(self::DB_PATH, 0777, true)) {
				cli_error("CampusesDatabase: Fatal: Unable to create database directory.", true);
			}
		}
		try {
			if (!file_exists(self::DB_PATH . self::DB_FILE))
				$new_db = true;
			open_database($this, self::DB_PATH . self::DB_FILE);
		}
		catch (Exception $e) {
				cli_error("CampusesDatabase: Fatal: Unable to open campuses.db.", true);
		}
		if ($new_db)
			$this->make_table();
	}

	/**
	 * Creates the campuses table if it does not exist
	 */
	private function make_table() {
		$result = $this->query(
			"CREATE TABLE IF NOT EXISTS campuses(
				data BLOB
			);"
		);
		if ($result === false) {
			cli_error_log("CampusesDatabase: could not make table. Reason: "
				.$this->lastErrorMsg());
		}
	}

	/**
	 * Returns the campus table, fetching it if needed
	 */
	function	access_campuses() {
		if ($this->campuses)
			return ($this->campuses);
		else {
			$result = $this->query("SELECT data FROM campuses");
			if ($result === false) {
				cli_error_log("CampusesDatabase: could not access campuses. Reason: "
					.$this->lastErrorMsg());
				return (false);
			}
			$tmp = $result->fetchArray();
			$str = null;
			if ($tmp === false)
				;//no records
			else if (!$tmp)
				cli_error_log("CampusesDatabase: access_campuses: could not fetch query results.");
			else
				$str = $tmp['data'];
			if ($str)
				$this->campuses = json_decode($str, true);
			if (!($this->campuses)) {
				$this->campuses = array();
				cli_error_log("CampusesDatabase: access_campuses: Warning: No campus data detected.");
			}
			return ($this->campuses);
		}
	}

	/**
	 * Updates the campus table
	 */
	function	update_campuses(IFtApiHandler $api) {
		$raw = $api->get('/v2/campus', array(
			'page[size]' => 100
		));
		if (!($raw)) {
			cli_error_log("CampusesDatabase: could not save campuses table: no data loaded");
			return (false);
		}
		$this->campuses = $this->pare_campus_data($raw);
		if (($result = $this->query("DELETE FROM campuses")) === false) {
			cli_error_log("CampusesDatabase: could not save campuses table (delete step): Reason: "
				.$this->lastErrorMsg());
			return (false);
		}
		$ins = $this->prepare("INSERT INTO campuses(data) VALUES (:campus_data);");
		$json = json_encode($this->campuses);
		$ins->bindValue(':campus_data', $json, SQLITE3_BLOB);
		$result = $ins->execute();
		if ($result === false) {
			cli_error_log("CampusesDatabase: could not save campuses table (insert step): Reason: "
				.$this->lastErrorMsg());
			return (false);
		}
		return (true);
	}

	/**
	 * Pares down the data from the api call
	 */
	private function pare_campus_data($raw) {
		$ret = array();
		foreach ($raw as $i => $entry) {
			$campus = array();
			$campus['campus_id'] = $entry['id'];
			$campus['name'] = $entry['name'];
			$campus['time_zone'] = $entry['time_zone'];
			$campus['language'] = $entry['language']['identifier'];
			$campus['country'] = $entry['country'];
			$campus['city'] = $entry['city'];
			$campus['address'] = $entry['address'];
			$campus['zip'] = $entry['zip'];
			$ret[$campus['campus_id']] = $campus;
		}
		return ($ret);
	}
}

?>
