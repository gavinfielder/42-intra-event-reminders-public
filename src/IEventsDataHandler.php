<?php

interface IEventsDataHandler {
	public function access_events();
	public function insert_event($event_array);
	public function add_user_to_event($event_id, $user);
	public function remove_outdated_events();

	//If sch is an instance of INotificationScheduleHandler, will
	//reschedule notifications for events that change time
	public function update_events($sch);

	//If sch is an instance of INotificationScheduleHandler, will schedule notifications
	public function update_event_users($sch);

	public function release_cache();
}

?>
