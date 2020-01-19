<?php

require_once(__DIR__ . '/../settings.php');
require_once(__DIR__."/logging.php");
require_once(__DIR__."/error.php");
require_once(__DIR__."/ISMSOptInHandler.php");
require_once(__DIR__.'/DatabaseManager.php');

class SMSOptInHandler extends SQLite3 implements ISMSOptInHandler {
	const DB_PATH = __DIR__."/../databases/";
	const DB_FILE = "sms-optin.db";

	function __construct() {
		$this->initialize();
	}

	function __destruct() {

	}

	private function initialize() {
		$new_db = false;
		if (!(file_exists(self::DB_PATH))) {
			$new_db = true;
			if (!mkdir(self::DB_PATH, 0777, true)) {
				contextual_error("SMSOptInHandler: Fatal: Unable to create database directory.", true);
			}
		}
		try {
			if (!(file_exists(self::DB_PATH.self::DB_FILE)))
				$new_db = true;
			open_database($this, self::DB_PATH.self::DB_FILE);
		}
		catch(Exception $e) {
			contextual_error("SMSOptInHandler: Fatal: Unable to open sms-optin.db.", true);
		}
		if ($new_db)
			$this->make_table();
	}

	private function make_table() {
		$result = $this->query(
			"CREATE TABLE IF NOT EXISTS optin(
				number TEXT PRIMARY KEY,
				status TEXT NOT NULL,
				code_generated TEXT,
				code_received TEXT,
				confirmed_date TEXT
			);");
		if ($result === false) {
			contextual_error_log("SMSOptInHandler: could not make table. Reason: "
				.$this->lastErrorMsg());
		}
	}

	public function get_optin_info($number) {
		$result = $this->query(
			"SELECT * FROM optin WHERE number = '$number';");
		if ($result !== false) {
			$arr = $result->fetchArray(SQLITE3_ASSOC);
			if ($arr !== false) {
				return ($arr);
			}
			else {
				//This number is not in the database
				$record = array(
					'number' => $number,
					'status' => 'new',
					'code_generated' => null,
					'code_received' => null,
					'confirmed_date' => null
				);
				$ret = $this->upsert_record($record);
				//TODO: should we check success? does it matter?
				return ($record);
			}
		}
		else {
			contextual_error_log("SMSOptInHandler: get_optin_info: query failed. Reason: "
				.$this->lastErrorMsg());
			return (false);
		}
	}

	public function get_optin_status($number) {
		$record = $this->get_optin_info($number);
		if (isset($record['status'])) {
			return ($record['status']);
		}
		return ($record);
	}

	private function upsert_record($record) {
		if (!is_array($record)) {
			contextual_log_error("SMSOptinHandler: upsert_record: invalid input. record=".var_export($record, true));
			return (false);
		}
		$result1 = $this->query(
			"DELETE FROM optin WHERE number = '".$record['number']."';");
		$query = $this->prepare("INSERT INTO optin
			(number, status, code_generated, code_received, confirmed_date)
			VALUES (:number_in, :status_in, :code_gen_in, :code_rec_in, :confirm_date_in);");
		$query->bindValue(':number_in', $record['number']);
		$query->bindValue(':status_in', $record['status']);
		$query->bindValue(':code_gen_in', $record['code_generated']);
		$query->bindValue(':code_rec_in', $record['code_received']);
		$query->bindValue(':confirm_date_in', $record['confirmed_date']);
		$result2 = $query->execute();
		if ($result2 === false) {
			contextual_error_log("SMSOptInHandler: upsert record failed. record is:\n".print_r($record, true)
				."Reason: ".$this->lastErrorMsg());
			return (false);
		}
		contextual_log("SMSOptInHandler: Updated record for number ".$record['number'].": status='".$record['status']."'.");
		return (true);
	}

	public function generate_confirmation_code($number) {
		$record = $this->get_optin_info($number);
		if ($record === false || !is_array($record)) {
			contextual_error_log("SMSOptInHandler: Could not generate confirmation code for number ".$record['number'].":  get_optin_info failed.");
			return (false);
		}
		if (is_string($record['code_generated']) && strlen($record['code_generated']) == 6) {
			return ($record['code_generated']);
		}
		else {
			$record['code_generated'] = strval(random_int(100000, 999999));
			$record['status'] = 'awaiting_confirmation';
			if ($this->upsert_record($record)) {
				return ($record['code_generated']);
			}
			else {
				contextual_error_log("SMSOptInHandler: Could not generate confirmation code for number ".$record['number'].": upsert_record failed.");
				return (false);
			}
		}
	}

	public function confirm($number, $confirmation_code) {
		$record = $this->get_optin_info($number);
		if ($record === false || !is_array($record)) {
			contextual_error_log("SMSOptInHandler: Could not confirm number ".$record['number'].": get_optin_info failed.");
			return (false);
		}
		if (is_string($record['code_generated']) && strlen($record['code_generated']) == 6) {
			if (trim(strval($confirmation_code)) == $record['code_generated']) {
				$record['code_received'] = trim(strval($confirmation_code));
				$record['status'] = 'confirmed';
				$record['confirmed_date'] = date('Y-m-d H:i:s');
				if ($this->upsert_record($record)) {
					return (true);
				}
				else {
					contextual_error_log("SMSOptInHandler: Could not confirm number ".$record['number'].": upsert_record failed.");
					return (false);
				}
			}
			else {
				if (DEBUG) {
					contextual_log("SMSOptInHanlder: ".$record['number']." entered incorrect code '"
						.$confirmation_code."'. Record:\n"
						.print_r($record, true));
				}
				return (false);
			}
		}
		else {
			contextual_error_log("SMSOptInHandler: Could not confirm number ".$record['number'].": confirmation code is not yet generated.");
			return (false);
		}
	}
}

?>
