<?php

interface	INotificationScheduleHandler {

	/**
	 * Functions for scheduling of reminders.
	 */
	public function	schedule_notifications($user_id, $event_id);
	public function schedule_notifications_all_user_events($user_id);
	public function schedule_notifications_all_event_users($event_id);
	public function schedule_notifications_all_events();

	/**
	 * Functions for removal of scheduled reminders.
	 */
	public function clear_all_scheduled_reminders();
	public function clear_scheduled_reminders_for_user($user_id);
	public function clear_scheduled_reminders_for_event($event_id);
	public function clear_scheduled_reminder($user_id, $event_id, $reminder_id);

	/**
	 * Executing scheduled tasks.
	 */
	public function get_ready_reminders();

	/**
	 * Tracking
	 */
	public function time_to_next_reminder();

	/**
	 * Validation
	 */
	public function is_event_start_changed($event_id);
	public function verify_reminder_list(&$reminders);

}

?>
