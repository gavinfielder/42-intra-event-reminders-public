<?php

interface ITaskMaster {

	//Execution
	public function run_next_task();

	//Tasks
	public function update_event_users();
	public function update_events();
	public function update_campuses();
	public function handle_notifications();
	public function update_slack_directory(int $campus_id);
	public function refresh_authentication();

	//Information
	public function time_to_next_notification();
}

?>
