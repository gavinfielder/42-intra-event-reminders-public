<?php

require_once(__DIR__.'/../settings.php');
require_once(__DIR__.'/error.php');
require_once(__DIR__.'/logging.php');
require_once(__DIR__.'/../vendor/autoload.php');
require_once(__DIR__.'/SlackDataHandler.php');
require_once(__DIR__.'/filters.php');
require_once(__DIR__.'/ISlackHandler.php');
require_once(__DIR__.'/IEventReminderSettingsHandler.php');

/**
 * SlackHandler handles slack. This is the class that is used to
 * send slack message notifications.
 *
 *   Methods
 *     SlackHandler::Create(campus_id)           create the handler for the given campus
 *     send_slack_reminder(user, event, token)   send a slack reminder message
 *
 *   Read-Only Properties
 *     db                                        access the SlackDataHandler component
 *                                               (use this to access public methods
 *                                                in this component)
 */
class	SlackHandler implements ISlackHandler {
	private $slack_token;
	private $slack_bot_user_id;
	private $campus_id;
	private $_db; //SlackDataHandler object
	private $client; //wrapi\slack\slack object

	//Allows _db to be a read-only public property under the title 'db'
	public function __get($name) {
		if ($name == "db") {
			return ($_db);
		}
		else {
			throw new Exception("$name is not an accessible property of SlackHandler");
		}
	}

	//Private so it can't be instantiated outside of factory method
	private function __construct(int $campus_id) {
		$this->campus_id = $campus_id;
		$this->initialize();
	}

	/**
	 * Factory method constructor
	 */
	static function	Create(int $campus_id) {
		switch ($campus_id) {
		case 7: //Fremont Campus
			$sh = new SlackHandler($campus_id);
			$sh->slack_token = SLACK_FREMONT_TOKEN;
			$sh->slack_bot_user_id = SLACK_FREMONT_BOT_USER_ID;
			$sh->client = new wrapi\slack\slack($sh->slack_token);
			return ($sh);
		default:
			cli_error_log("SlackHandler: could not instantiate: campus $campus_id not handled.");
			return (false);
		}
	}

	private function initialize() {
		$this->_db = SlackDataHandler::Create($this->campus_id);
	}

	/**
	 * Sends a slack reminder to the given user and the given event
	 *
	 * If the user is not yet associated with a slack id, this function
	 * attempts to associate the user with a slack id. This is an
	 * expensive operation that is dependent on the slack_directory table
	 *
	 * TODO: analyze function for cyclomatic complexity and dependencies.
	 * The way slack messages are sent could probably use some refactoring,
	 * based on this being a huge function and having some questionable
	 * dependencies.
	 * EDIT: might be better since IEventReminderSettingsHandler was
	 * integrated in, but it's still a huge function.
	 */
	function	send_slack_reminder($user, $event, IEventReminderSettingsHandler $sets) {

		//Validate input
		if (!isset($user['user_id']) || !(isset($event['event_id']))) {
			cli_error_log("Could not send slack event reminder: invalid input"
				."\n    user=\"".var_export($user, true)."\""
				."\n    event=\"".var_export($event, true)."\"");
			return (false);
		}
		$user_id = $user['user_id'];
		$event_id = $event['event_id'];

		//Get slack id
		$slack_id = $this->_db->get_slack_id($user_id);
		if ($slack_id === false) {
			cli_error_log("SlackHandler: could not send slack message: database query failed");
			return (false);
		}
		//if (slack_id === null) then we'll have to identify the user, which is
		//an expensive operation. Perform other validations first.

		//Set timezone
		try {
			$time = new DateTime($event['begin_at']);
			$db = new CampusesDatabase();
			$campuses = $db->access_campuses();
			$campus = $campuses[$event['campus_id']];
			$time->setTimezone(new DateTimeZone($campus['time_zone']));
		}
		catch (Exception $ex) {
			cli_error_log("SlackHandler: Could not format message body for user $user_id: could not load timezone");
			return (false);
		}

		//If this user is not yet associated with a slack id, attempt
		//to find the proper slack user to associate with this user
		if ($slack_id === null) {
			$user_settings = $sets->get_user_settings($user_id);
			if ($user_settings === false) {
				cli_error_log("SlackHandler: could not send slack message: could not get user settings");
				return (false);
			}
			if (isset($user_settings['reminder_platforms_json']['user-slack'])) {
				$slack_input = $user_settings['reminder_platforms_json']['user-slack'];
			}
			else {
				cli_error_log("SlackHandler: Warning: $user_id has no slack input set");
				$slack_input = '';
			}
			//identify_user is an expensive operation
			$slack_dir_entry = $this->_db->identify_user($slack_input, $user);
			if (isset($slack_dir_entry['slack_id'])) {
				$slack_id = $slack_dir_entry['slack_id'];
			}
			else {
				cli_error_log("SlackHandler: could not send slack message: identify_user failed.");
				return (false);
			}
		}
		//At this point, $slack_id should be guaranteed to be set

		//For testing: If ONLY_SEND_TO_DEV_WHITELIST is true, then filter based on username
		if (ONLY_SEND_TO_DEV_WHITELIST && dev_filter_user_is_whitelisted($user) !== true) {
			cli_log("SlackHandler: aborting sending slack message: user $user_id (".$user['login'].") is not whitelisted.");
			return (true);
		}

		//Open an instant message channel with the user
		$conv = $this->client->im->open(array(
			'user' => $slack_id
		));
		if (!(isset($conv['ok']) && $conv['ok'])) {
			cli_error_log("SlackHandler: could not send slack message: failed to open messaging channel. "
				."Reason: ".$conv['error']
			);
			return (false);
		}
		else
			$channel_id = $conv['channel']['id'];

		//Format the message
		$msg = use_template('slack', array(
			'user' => $user,
			'event' => $event,
			'time' => $time
		));

		//Send the messsage
		$result = $this->client->chat->postMessage(array(
			'channel' => $channel_id,
			'text' => $msg
		));

		//Check the result
		if (isset($result['ok']) && $result['ok']) {
			return (true);
		}
		cli_error_log("SlackHandler: could not send slack message: failed to send the message. "
			."Reason: ".$reason['error']
		);
		return (false);
	}

	/**
	 * Other functions needed by ISlackHandler but passed through
	 * to the SlackDataHandler component
	 */
	public function	update_slack_data() {
		return ($this->_db->update_slack_directory());
	}
	public function get_slack_id($user_id) {
		return ($this->_db->get_slack_id($user_id));
	}
	public function	identify_user($query_for, $user_array) {
		return ($this->_db->identify_user($query_for, $user_array));
	}
}

?>
