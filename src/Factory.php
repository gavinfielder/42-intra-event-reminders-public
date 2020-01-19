<?php

//Basic function sets
require_once(__DIR__ . '/../settings.php');
require_once(__DIR__."/logging.php");
require_once(__DIR__."/error.php");
require_once(__DIR__.'/auth.php');

//Interfaces
require_once(__DIR__.'/IEventsDataHandler.php');
require_once(__DIR__.'/ICampusesDataHandler.php');
require_once(__DIR__.'/IFtApiHandler.php');
require_once(__DIR__.'/INotificationScheduleHandler.php');
require_once(__DIR__.'/ISMSHandler.php');
require_once(__DIR__.'/ISlackHandler.php');
require_once(__DIR__.'/IWhatsAppHandler.php');
require_once(__DIR__.'/IAuthenticationHandler.php');
require_once(__DIR__.'/ITaskMaster.php');
require_once(__DIR__.'/IEventReminderSettingsHandler.php');
require_once(__DIR__.'/ISMSOptInHandler.php');

//Implementations
require_once(__DIR__.'/EventsDatabase.php');
require_once(__DIR__.'/FtApiHandler.php');
require_once(__DIR__.'/AuthenticationHandler.php');
require_once(__DIR__.'/CampusesDatabase.php');
require_once(__DIR__.'/TaskMaster.php');
require_once(__DIR__.'/NotificationScheduleHandler.php');
require_once(__DIR__.'/EventReminderSettingsHandler.php');
require_once(__DIR__.'/EmailHandler.php');
require_once(__DIR__.'/TwilioHandler.php');
require_once(__DIR__.'/SlackHandler.php');
require_once(__DIR__.'/FakeFtApiHandler.php');
require_once(__DIR__.'/SMSOptInHandler.php');

/**
 * The factory class makes instances of interfaces.
 *
 * It keeps references to all instances created by
 * the factory, and so can enforce singleton if needed.
 */
class	EventRemindersFactory {

	//This is a static class only, so no instances of this class can be created
	private function __construct() {}

	//static initializers won't work in old versions of PHP. TODO: find out how old
	private static $_IEventsDataHandlers = array();
	private static $_ICampusesDataHandlers = array();
	private static $_IFtApiHandlers = array();
	private static $_INotificationScheduleHandlers = array();
	private static $_ISMSHandlers = array();
	private static $_ISMSOptInHandlers = array();
	private static $_ISlackHandlers = array();
	private static $_IWhatsAppHandlers = array();
	private static $_IAuthenticationHandlers = array();
	private static $_ITaskMasters = array();
	private static $_IEventReminderSettingsHandlers = array();
	private static $_IEmailHandlers = array();
	private static $_Fake42Apis = array();

	/**
	 * The factory method. Give it the name of an interface and whether
	 * to enforce singleton.
	 */
	public static function Create(string $interface_name, bool $singleton, $args = null) {

		switch ($interface_name) {

		case 'IFtApiHandler':
			if (USE_FAKE_42_API && php_sapi_name() == 'cli') {
				if ($singleton && count(static::$_Fake42Apis) > 0) {
					return (static::$_Fake42Apis[0]);
				}
				$api = new FakeFtApiHandler();
				static::$_Fake42Apis[] = $api;
				return ($api);
			}
			if ($singleton && count(static::$_IFtApiHandlers) > 0) {
				return (static::$_IFtApiHandlers[0]);
			}
			$auth = self::Create('IAuthenticationHandler', true);
			$token = ($auth ? $auth->authorize() : null);
			if (!$token) {
				contextual_error_log("Factory: Could not authenticate with 42 api");
				return (null);
			}
			$api = new FtApiHandler($token);
			static::$_IFtApiHandlers[] = $api;
			return ($api);


		case 'IAuthenticationHandler':
			if ($singleton && count(static::$_IAuthenticationHandlers) > 0) {
				return (static::$_IAuthenticationHandlers[0]);
			}
			if (php_sapi_name() == 'cli') {
				$auth = new CliAuthenticationHandler();
				static::$_IAuthenticationHandlers[] = $auth;
				return ($auth);
			}
			else {
				$auth = new HttpAuthenticationHandler();
				static::$_IAuthenticationHandlers[] = $auth;
				return ($auth);
			}

		case 'INotificationScheduleHandler':
			if ($singleton && count(static::$_INotificationScheduleHandlers) > 0)
				return (static::$_INotificationScheduleHandlers[0]);
			if (USE_FAKE_42_API && php_sapi_name() != "cli")
				$api = new FakeFtApiHandler();
			else
				$api = Self::Create('IFtApiHandler', $singleton);
			$events_handler = Self::Create('IEventsDataHandler', $singleton);
			$scheduler = new NotificationScheduleHandler($api, $events_handler);
			static::$_INotificationScheduleHandlers[] = $scheduler;
			return ($scheduler);

		case 'IEventReminderSettingsHandler':
			if ($singleton && count(static::$_IEventReminderSettingsHandlers) > 0)
				return (static::$_IEventReminderSettingsHandlers[0]);
			$api = Self::Create('IFtApiHandler', $singleton);
			$settings = new EventReminderSettingsHandler($api);
			static::$_IEventReminderSettingsHandlers[] = $settings;
			return ($settings);

		case 'ICampusesDataHandler':
			if ($singleton && count(static::$_ICampusesDataHandlers) > 0) {
				return (static::$_ICampusesDataHandlers[0]);
			}
			$camp = new CampusesDatabase();
			static::$_ICampusesDataHandlers[] = $camp;
			return ($camp);

		case 'IEventsDataHandler':
			if ($singleton && count(static::$_IEventsDataHandlers) > 0) {
				return (static::$_IEventsDataHandlers[0]);
			}
			$api = Self::Create('IFtApiHandler', $singleton);
			if (!($api)) {
				contextual_error_log("Factory: Could not create depencencies for $interface_name.");
				return (null);
			}
			$edh = new EventsDatabase($api);
			static::$_IEventsDataHandlers[] = $edh;
			return ($edh);

		case 'IEmailHandler':
			if ($singleton && count(static::$_IEmailHandlers) > 0)
				return (static::$_IEmailHandlers[0]);
			$email = new EmailHandler();
			static::$_IEmailHandlers[] = $email;
			return ($email);


		case 'ISlackHandler':
			if ($singleton && count(static::$_ISlackHandlers) > 0)
				return (static::$_ISlackHandlers[0]);
			if (!isset($args['campus_id'])) {
				contextual_error_log("Factory: Could not create $interface_name. Needs args['campus_id'].\n");
				return (null);
			}
			$slack = SlackHandler::Create($args['campus_id']);
			static::$_ISlackHandlers[] = $slack;
			return ($slack);


		case 'ISMSOptInHandler':
			if ($singleton && count(static::$_ISMSOptInHandlers) > 0)
				return (static::$_ISMSOptInHandlers[0]);
			$optin = new SMSOptInHandler();
			static::$_ISMSOptInHandlers[] = $optin;
			return ($optin);


		case 'ISMSHandler':
			if ($singleton && count(static::$_ISMSHandlers) > 0)
				return (static::$_ISMSHandlers[0]);
			$optin = Self::Create('ISMSOptInHandler', $singleton);
			if (!$optin) {
				contextual_error_log("Factory: Could not create depencencies for $interface_name.");
				return (null);
			}
			$sms = new TwilioHandler($optin);
			static::$_ISMSHandlers[] = $sms;
			return ($sms);


		case 'IWhatsAppHandler':
			if ($singleton && count(static::$_IWhatsAppHandlers) > 0)
				return (static::$_IWhatsAppHandlers[0]);
			$optin = Self::Create('ISMSOptInHandler', $singleton);
			if (!$optin) {
				contextual_error_log("Factory: Could not create depencencies for $interface_name.");
				return (null);
			}
			$whatsapp = new TwilioHandler($optin);
			static::$_IWhatsAppHandlers[] = $whatsapp;
			return ($whatsapp);


		case 'ITaskMaster':
			if ($singleton && count(static::$_ITaskMasters) > 0) {
				return (static::$_ITaskMasters[0]);
			}
			$api = Self::Create('IFtApiHandler', $singleton);
			$cdh = Self::Create('ICampusesDataHandler', $singleton);
			$nsh = Self::Create('INotificationScheduleHandler', $singleton);
			$auth = Self::Create('IAuthenticationHandler', $singleton);
			$edh = Self::Create('IEventsDataHandler', $singleton);
			$email = Self::Create('IEmailHandler', $singleton);
			$slack_fremont = Self::Create('ISlackHandler', $singleton, array(
				'campus_id' => 7
			));
			$sms = Self::Create('ISMSHandler', $singleton);
			$whatsapp = Self::Create('IWhatsAppHandler', $singleton);
			$sets = Self::Create('IEventReminderSettingsHandler', $singleton);
			if (!$api || !$cdh || !$auth || !$edh) {
				contextual_error_log("Factory: Could not create depencencies for $interface_name.");
				return (null);
			}
			$tm = new TaskMaster($api, $cdh, $nsh, $auth, $edh, $email,
				array(
					7 => $slack_fremont
				), $sms, $whatsapp, $sets);
			return ($tm);

		default:
			contextual_error_log("Factory: Could not instantiate '$interface_name'. Is the name correct?");
			return (null);
		}
	}

}

?>
