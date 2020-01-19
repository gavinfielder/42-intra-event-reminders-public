#!/usr/bin/php
<?php

require_once(__DIR__.'/settings.php');
require_once(__DIR__.'/src/error.php');
require_once(__DIR__.'/src/logging.php');
require_once(__DIR__.'/src/Factory.php');

$tm = EventRemindersFactory::Create('ITaskMaster', true);
$min_sleep_time = 2;
$max_sleep_time = 5;

if ($tm) {
	echo "Initializing: Updating event users.\n";
	$tm->update_event_users();
	while (1) {
		$result = $tm->run_next_task();
		$msg = "run.php: ".$result['task_name']
			." completed in ".$result['seconds_taken']." seconds.";
		if (isset($result['success'])) $msg .= ($result['success'] ? " Success." : " Failure.");
		if (isset($result['n'])) $msg .= (" ".$result['n_name']." ".$result['n']." items.");
		if ($result['forced']) $msg .= " (forced)";
		cli_log($msg);
		$sleep_time = $max_sleep_time - $result['seconds_taken'];
		if ($sleep_time > $max_sleep_time) $sleep_time = $max_sleep_time;
		else if ($sleep_time < $min_sleep_time) $sleep_time = $min_sleep_time;
		sleep($sleep_time);
	}
}

?>
