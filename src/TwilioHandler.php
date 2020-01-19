<?php

require_once(__DIR__.'/../settings.php');
require_once(__DIR__.'/error.php');
require_once(__DIR__.'/logging.php');
require_once(__DIR__.'/../vendor/autoload.php');
require_once(__DIR__.'/CampusesDatabase.php');
require_once(__DIR__.'/filters.php');
require_once(__DIR__.'/templating.php');
require_once(__DIR__.'/IWhatsAppHandler.php');
require_once(__DIR__.'/ISMSHandler.php');
require_once(__DIR__.'/IEventReminderSettingsHandler.php');
require_once(__DIR__.'/ISMSOptInHandler.php');

class		TwilioHandler implements IWhatsAppHandler, ISMSHandler {
	private const ACCOUNT_SID = TWILIO_ACCOUNT_SID;
	private const AUTH_TOKEN = TWILIO_AUTH_TOKEN;
	private const WHATSAPP_NUMBER = TWILIO_AUTH_TOKEN;
	private $optin; //ISMSOptInHandler

	function __construct(ISMSOptInHandler $optin_in) {
		$this->optin = $optin_in;
	}
	
	public function send_sms_reminder($user, $event, IEventReminderSettingsHandler $sets) {
		if (!isset($user['user_id']) || !(isset($event['event_id']))) {
			cli_error_log("TwilioHandler: Could not send sms event reminder: invalid input"
				."\n    user=\"".var_export($user, true)."\""
				."\n    event=\"".var_export($event, true)."\"");
			return (false);
		}
		$user_id = $user['user_id'];
		$event_id = $event['event_id'];
		$user_settings = $sets->get_user_settings($user_id);
		if ($user_settings === false) {
			cli_error_log("TwilioHandler: Could not send sms reminder: could not fetch settings for user $user_id");
			return (false);
		}
		if (!(isset($user_settings['reminder_platforms_json']['user-sms']))) {
			cli_error_log("TwilioHandler: Could not send sms event reminder: user $user_id has no sms set");
			return (false);
		}
		$to = $user_settings['reminder_platforms_json']['user-sms'];
		try {
			$time = new DateTime($event['begin_at']);
			$db = new CampusesDatabase();
			$campuses = $db->access_campuses();
			$campus = $campuses[$event['campus_id']];
			$time->setTimezone(new DateTimeZone($campus['time_zone']));
		}
		catch (Exception $ex) {
			cli_error_log('TwilioHandler: Could not format sms body for user '.$user['user_id'].': could not load timezone');
			return (false);
		}
		$msg = use_template('sms', array(
			'user' => $user,
			'event' => $event,
			'time' => $time
		));
		if (strlen($msg) > 160)
			$msg = substr($msg, 0, 160);

		//For testing: If ONLY_SEND_TO_DEV_WHITELIST is true, then filter based on username
		if (ONLY_SEND_TO_DEV_WHITELIST && dev_filter_user_is_whitelisted($user) !== true) {
			cli_log("TwilioHandler: Send sms aborted: user $user_id (".$user['login'].") is not whitelisted.");
			return (true);
		}

		//Check if this number has opted in
		if ($this->optin->get_optin_status($to) != 'confirmed') {
			cli_error_log("TwilioHandler: Send sms aborted: user $user_id (".$user['login'].") has not confirmed opt-in.");
			return (false);
		}
	
		$client = new Twilio\Rest\Client(Self::ACCOUNT_SID, Self::AUTH_TOKEN);
		try {
			$result = $client->messages->create(
				$to,
				array(
					'from' => TWILIO_NUMBER,
					'body' => $msg
				)
			);
			return (true);
		}
		catch (Exception $ex) {
			cli_error_log("TwilioHandler: Could not send sms to user $user_id: Send failed. Reason: "
				.$ex->getMessage());
			return (false);
		}
	}

	public function send_confirmation_request($number, $confirmation_code) {
		$client = new Twilio\Rest\Client(Self::ACCOUNT_SID, Self::AUTH_TOKEN);
		try {
			$msg = use_template('confirmation_code', array(
				'confirmation_code' => $confirmation_code
			));
			if (!$msg) {
				contextual_error_log("TwilioHandler: Could not format confirmation message to number $number.");
				return (false);
			}
			$result = $client->messages->create(
				$number,
				array(
					'from' => TWILIO_NUMBER,
					'body' => $msg
				)
			);
			return (true);
		}
		catch (Exception $ex) {
			contextual_error_log("TwilioHandler: Could not send confirmation code to number $number: Send failed. Reason: "
				.$ex->getMessage());
			return (false);
		}
	}

	public function validate_number($input) {
		if (!is_string($input))
			return (false);
		$input = trim($input);
		//The following regex is for US phone numbers
		if (!preg_match('/^(\+?1\s?)?((\([0-9]{3}\))|[0-9]{3})[\s\-]?[\0-9]{3}[\s\-]?[0-9]{4}$/', $input))
			return (false);
		try {
			$client = new Twilio\Rest\Client(Self::ACCOUNT_SID, Self::AUTH_TOKEN);
			//Throws exception if number is not found
			$info = $client->lookups->v1->phoneNumbers($input)->fetch(array("countryCode" => "US"));
			return (true);
		}
		catch (Exception $ex) {
			return (false);
		}
	}

	function	send_whatsapp_reminder($user, $event, IEventReminderSettingsHandler $sets) {
		cli_error_log("TwilioHandler: Could not send whatsapp event reminder: not yet enabled");
		return (false);
		/*
		if (!isset($user['user_id']) || !(isset($event['event_id']))) {
			cli_error_log("Could not send whatsapp event reminder: invalid input"
				."\n    user=\"".var_export($user, true)."\""
				."\n    event=\"".var_export($event, true)."\"");
			return (false);
		}
		$user_id = $user['user_id'];
		$event_id = $event['event_id'];
		$user_settings = get_user_settings($user_id, $token);
		if ($user_settings === false) {
			cli_error_log("Could not send whatsapp reminder: could not fetch settings for user $user_id");
			return (false);
		}
		if (!(isset($user_settings['reminder_platforms_json']['user-whatsapp']))) {
			cli_error_log("Could not send whatsapp event reminder: user $user_id has no whatsapp set");
			return (false);
		}
		$to = $user_settings['reminder_platforms_json']['user-whatsapp'];
		try {
			$time = new DateTime($event['begin_at']);
			$db = new CampusesDatabase();
			$campuses = $db->access_campuses();
			$campus = $campuses[$event['campus_id']];
			$time->setTimezone(new DateTimeZone($campus['time_zone']));
		}
		catch (Exception $ex) {
			cli_error_log('Could not format whatsapp body for user '.$user['user_id'].': could not load timezone');
			return (false);
		}
		$msg = use_template('sms', array(
			'user' => $user,
			'event' => $event,
			'time' => $time
		));
		if (strlen($msg) > 160)
			$msg = substr($msg, 0, 160);
	
		//TEMP: Only send the message if this user is a developer for this project
		if (dev_filter_user_is_whitelisted($user) !== true) {
			cli_error_log("Send whatsapps aborted: user $user_id (".$user['login'].") is not whitelisted as a developer for this project.");
			return (false);
		}
	
		$client = new Twilio\Rest\Client(Self::ACCOUNT_SID, Self::AUTH_TOKEN);
		try {
			$result = $client->messages->create(
				"whatsapp:".$to,
				array(
					'from' => Self::WHATSAPP_NUMBER,
					'body' => $msg
				)
			);
			return (true);
		}
		catch (Exception $ex) {
			cli_error_log("Could not send whatsapp to user $user_id: Send failed. Reason: "
				.$ex->getMessage());
			return (false);
		}
		*/
	}
}

?>
