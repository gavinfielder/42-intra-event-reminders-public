<?php

require_once(__DIR__.'/../settings.php');
require_once(__DIR__.'/error.php');
require_once(__DIR__.'/logging.php');
require_once(__DIR__.'/../vendor/autoload.php');
require_once(__DIR__.'/IEventReminderSettingsHandler.php');
require_once(__DIR__.'/CampusesDatabase.php');
require_once(__DIR__.'/filters.php');
require_once(__DIR__.'/templating.php');
require_once(__DIR__.'/IEmailHandler.php');

class	EmailHandler implements IEmailHandler {
	function	email_event_reminder($user, $event, IEventReminderSettingsHandler $sets) {
		if (!isset($user['user_id']) || !(isset($event['event_id']))) {
			cli_error_log("Could not email event reminder: invalid input"
				."\n    user=\"".var_export($user, true)."\""
				."\n    event=\"".var_export($event, true)."\"");
			return (false);
		}
		$user_id = $user['user_id'];
		$event_id = $event['event_id'];
		$mail = $this->init_mail();
		$mail->Subject = "Reminder: ".$event['name'];
		$user_settings = $sets->get_user_settings($user_id);
		if ($user_settings === false) {
			cli_error_log("Could not email event reminder: could not fetch settings for user $user_id");
			return (false);
		}
		if (!(isset($user_settings['reminder_platforms_json']['user-email']))) {
			cli_error_log("Could not email event reminder: user $user_id has no email set");
			return (false);
		}
		//Fill the 'to' address
		$mail->addAddress($user_settings['reminder_platforms_json']['user-email']);
		//Fill the email body from the template
		if ($this->fill_mail_subject_body($mail, $user, $event, $user_settings['reminder_platforms_json']['user-email']) === false) {
			cli_error_log("Could not email event reminder: could not format email body");
			return (false);
		}

		//For testing: If ONLY_SEND_TO_DEV_WHITELIST is true, then filter based on username
		if (ONLY_SEND_TO_DEV_WHITELIST && dev_filter_user_is_whitelisted($user) !== true) {
			cli_log("EmailHandler: Send email aborted: user $user_id (".$user['login'].") is not whitelisted.");
			return (true);
		}
		//Send email
		if (!($mail->send())) {
			$error = "Mailer Error: " . $mail->ErrorInfo;
			cli_error_log("Could not email event reminder. Reason: $error");
			return false;
		}
		return (true);
	}
	
	//Sets up all the common values needed to send mail
	private function	init_mail()
	{
		$mail = new PHPMailer\PHPMailer\PHPMailer;
		$mail->isSMTP();
		$mail->Host = 'smtp.gmail.com';
		$mail->Port = 587;
		$mail->SMTPSecure = 'tls';
		$mail->SMTPAuth = true;
		$mail->From = '42eventreminders@gmail.com';
		$mail->FromName = '42 Intra Event Reminders Bot';
		$mail->Username = '42eventreminders@gmail.com';
		$mail->Password = EMAIL_PASSWORD;
		return ($mail);
	}
	
	private function	fill_mail_subject_body(PHPMailer\PHPMailer\PHPMailer &$mail,
										$user, $event, $to_address) {
		try {
			$time = new DateTime($event['begin_at']);
			$db = new CampusesDatabase();
			$campuses = $db->access_campuses();
			$campus = $campuses[$event['campus_id']];
			$time->setTimezone(new DateTimeZone($campus['time_zone']));
		}
		catch (Exception $ex) {
			cli_error_log('Could not format email body for user '.$user['user_id'].': could not load timezone');
			return (false);
		}
		$msg = use_template('email', array(
			'user' => $user,
			'event' => $event,
			'email_address' => $to_address,
			'time' => $time
		));
		$mail->msgHTML($msg);	
	}
}

?>
