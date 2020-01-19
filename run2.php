#!/usr/bin/php
<?php

require_once(__DIR__.'/src/Factory.php');

//===== usable data: =====
//  event 3176 is an event on July 5th that takes place 8AM UTC (10 AM Africa/Johannesburg)
//  user 44918 is gfielder
//  user 47428 is dcelojev
//  user 24435 is ikozlov (not batman)

//$api = EventRemindersFactory::Create('IFtApiHandler', true);
//print_r($api->get_current_token());
//exit(0);

$tm = EventRemindersFactory::Create('ITaskMaster', true);

if ($tm) {
	$tm->debug = true;
	$tm->update_events();
	$tm->update_event_users();
	$notif = $tm->time_to_next_notification();
	var_dump($notif);
}

?>
