<?php

require_once(__DIR__ . '/../settings.php');
require_once(__DIR__ . '/error.php');
require_once(__DIR__ . '/logging.php');
require_once(__DIR__ . '/Factory.php');
require_once(__DIR__ . '/reminders-database.php');

class NotificationScheduleHandler implements INotificationScheduleHandler {

	private $api;
	private $events_handler;
	private $schedule_db;
	private $settings_db;

	function __construct($api, $events_handler) {
		$this->api = $api;
		$this->events_handler = $events_handler;
		$this->open_database_connections();
	}

	function __destruct() {
		$this->schedule_db->close();
		$this->settings_db->close();
	}

	private function open_database_connections() {
		$this->schedule_db = new ReminderDatabase();
		$this->settings_db = new EventReminderSettingsDatabase($this->api);
	}

	private function get_events_data() {
		if (!$this->events_handler)
			return (false);
		return ($this->events_handler->access_events());
	}

	/**
	 * Deletes ALL scheduled reminders from the database.
	 */
	public function clear_all_scheduled_reminders() {
		$this->schedule_db->clear_table();
		contextual_log("NotificationScheduleHandler: Finished clearing all scheduled reminders.");
	}

	/**
	 * Deletes all scheduled reminders for a specified user id.
	 */
	public function clear_scheduled_reminders_for_user($user_id) {
		$del = $this->schedule_db->prepare("DELETE FROM " . ReminderDatabase::TABLE_NAME . " WHERE user_id=:user_id;");
		$del->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
		$del->execute();
	}

	public function clear_scheduled_reminders_for_event($event_id) {
		$del = $this->schedule_db->prepare("DELETE FROM " . ReminderDatabase::TABLE_NAME . " WHERE event_id=:event_id;");
		$del->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
		$del->execute();
	}

	public function clear_scheduled_reminder($user_id, $event_id, $reminder_id) {
		$del = $this->schedule_db->prepare("DELETE FROM " . ReminderDatabase::TABLE_NAME . " WHERE user_id=:user_id AND event_id=:event_id AND reminder_id=:reminder_id;");
		$del->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
		$del->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
		$del->bindValue(':reminder_id', $reminder_id, SQLITE3_INTEGER);
		$del->execute();
	}

	public function schedule_notifications_all_user_events($user_id) {
		$reminders_scheduled = 0;
		$user_events = $this->api->access_user_events($user_id);
		foreach ($user_events as $event) {
			$event_id = $event['event_id'];
			$campus_id = $event['campus_id'];
			$event_start_time = $event['begin_at'];
			$reminders_scheduled += $this->schedule_reminders($user_id, $event_id, $campus_id, $event_start_time);
		}
		contextual_log("NotificationScheduleHandler: Finished scheduling $reminders_scheduled reminders for user id $user_id.");
	}

	public function schedule_notifications_all_events() {
		$reminders_scheduled = 0;

		$events_data = $this->get_events_data();
		foreach ($events_data as $event) {
			$event_id = intval($event['event_id']);
			$event_users = $event['users'];
			$campus_id = intval($event['campus_id']);
			foreach ($event_users as $user) {
				$user_id = $user['user_id'];
				$event_start_time = $event['begin_at'];
				$reminders_scheduled += $this->schedule_reminders($user_id, $event_id, $campus_id, $event_start_time);
			}
		}
		contextual_log("NotificationScheduleHandler: Finished scheduling $reminders_scheduled notifications.");
	}

	/**
	 * Schedules reminders for a specific user and returns the amount of notification's scheduled (max: 3).
	 */
	//Todo check for empty/null data (then dont schedule). it will likely fail on empty amount but needs better validation than that for speed
	private function schedule_reminders($user_id, $event_id, $campus_id, $event_start_time) {
		$reminders_scheduled = 0;
		$user_settings = $this->settings_db->get_user_settings($user_id);

		if (empty($user_settings['reminder_platforms_json']))
			return (0);
		for ($reminder_id = 1; $reminder_id <= 3; $reminder_id++) //Three reminders to be checked and scheduled (if enabled).
			$reminders_scheduled += $this->schedule_reminder($user_id, $event_id, $campus_id, $reminder_id, $user_settings, $event_start_time);
		return ($reminders_scheduled);
	}

	private function schedule_reminder($user_id, $event_id, $campus_id, $reminder_id, $user_settings, $event_start_time) {
		if ($user_settings['reminder_times_json']['reminder-time-' . $reminder_id . '-platform'] !== 'disable'
				&& !$this->reminder_exists($user_id, $event_id, $reminder_id)) {
			$scheduled_time = $this->calculate_scheduled_time($event_start_time, $user_settings['reminder_times_json'], $reminder_id);
			if ($scheduled_time) {
				$this->schedule_db->schedule_reminder($user_id, $campus_id, $event_id, $reminder_id, $scheduled_time);
				return (1);
			}
		}
		return (0);
	}

	private function reminder_exists($user_id, $event_id, $reminder_id) {
		return ($this->schedule_db->reminder_exists($user_id, $event_id, $reminder_id));
	}

	private function is_event_user($user_id, $event_users) {
		return (array_key_exists($user_id, $event_users));
	}

	public function schedule_notifications($user_id, $event_id) {
		$event = $this->get_events_data()[$event_id];
		if ($this->is_event_user($user_id, $event['users'])) {
			$event_start_time = $event['begin_at'];
			$campus_id = $event['campus_id'];
			$scheduled_reminders = $this->schedule_reminders($user_id, $event_id, $campus_id, $event_start_time);
			if ($scheduled_reminders > 0)
				contextual_log("NotificationScheduleHandler: Scheduled $scheduled_reminders for user id $user_id for event id $event_id.");
			else
				contextual_log("NotificationScheduleHandler: No reminders need to be scheduled for user id $user_id for event id $event_id.");
		} else
			contextual_error_log("NotificationScheduleHandler: User id $user_id is not registered for event id $event_id.");
	}

	public function schedule_notifications_all_event_users($event_id) {
		$event = $this->get_events_data()[$event_id];
		$event_start_time = $event['begin_at'];
		$campus_id = $event['campus_id'];
		$users = $event['users'];
		$reminders_scheduled = 0;

		foreach ($users as $user) {
			$user_id = $user['user_id'];
			$reminders_scheduled += $this->schedule_reminders($user_id, $event_id, $campus_id, $event_start_time);
		}
		contextual_log("NotificationScheduleHandler: Finished scheduling $reminders_scheduled notifications for event id $event_id.");
	}

	public function get_ready_reminders() {
		$time = time();
		$ready = $this->schedule_db->prepare("SELECT user_id, campus_id, event_id, reminder_id, scheduled_time FROM scheduled_reminders WHERE scheduled_time <= :now ORDER BY scheduled_time ASC;");
		$ready->bindValue(':now', $time, SQLITE3_INTEGER);
		$res = $ready->execute();
		$ready_reminders = [];
		while ($row = $res->fetchArray(SQLITE3_ASSOC))
			$ready_reminders[] = $row;
		return ($ready_reminders);
	}

	public function time_to_next_reminder() {
		$next_reminder_data = $this->schedule_db->query("SELECT scheduled_time FROM " . ReminderDatabase::TABLE_NAME . " ORDER BY scheduled_time ASC LIMIT 1;");
		$next_reminder = $next_reminder_data->fetchArray(SQLITE3_ASSOC)['scheduled_time'];
		return ($next_reminder - time());
	}

	private function calculate_scheduled_time($event_start_time, $reminder_time_json, $reminder_id) {
		$reminder_time_amount = $reminder_time_json['reminder-time-' . $reminder_id . '-amount'];
		$reminder_time_unit = $this->read_unit(trim($reminder_time_json['reminder-time-' . $reminder_id . '-unit']));
		if (!$reminder_time_unit || !$this->is_valid_time_data($reminder_time_amount) || !$this->is_valid_time_data($reminder_time_unit))
			return (false);
		if ($reminder_time_amount < 0)
			$reminder_time_amount *= -1;
		$scheduled_time = strtotime("-" . $reminder_time_amount . " " . $reminder_time_unit, strtotime($event_start_time));
		$scheduled_time = intval($scheduled_time);
		return (time() > $scheduled_time ? false : $scheduled_time);
	}
	
	private function is_valid_time_data($var) {
		return (!is_null($var) && !empty($var));
	}
	
	private function read_unit($selected_unit) {
		if (is_int(strpos($selected_unit, "day")))
			return ("day");
		else if (is_int(strpos($selected_unit, "hour")))
			return ("hour");
		else if (is_int(strpos($selected_unit, "minute")))
			return ("minute");
		return (false);
	}
	
	private function ft_epoch_to_date($epoch) {
		$datetime = new DateTime("@$epoch");
		return $datetime->format('Y-m-d\TH:i:s\Z');
	}

	/**
	 * Returns true if an event's start time has changed.
	 */
	public function is_event_start_changed($event_id) {
		$local_event_start = $this->get_events_data()[$event_id]['begin_at'];
		$api_event_start = $this->api->access_event($event_id)['begin_at'];
		return (strtotime($local_event_start) == strtotime($api_event_start));
	}

	/**
	 * Verifies that users are still registered for the events that the reminders are set for.
	 */
	public function verify_reminder_list(&$reminders) {
		foreach ($reminders as $key=>$reminder) {
			$user_id = $reminder['user_id'];
			$event_id = $reminder['event_id'];
			$event_users = $this->api->access_event_users($event_id);
			//Note this will mark as invalid when the api call fails
			if (isset($event_users[$user_id]))
				$reminders[$key]['valid'] = true;
			else
				$reminders[$key]['valid'] = false;
		}
	}
}
?>
