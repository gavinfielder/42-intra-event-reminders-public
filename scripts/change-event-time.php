#!/usr/bin/php
<?php

require_once(__DIR__.'/functions/input.php');

date_default_timezone_set('America/Tijuana');
if (file_exists(__DIR__.'/../databases/fake_events.txt'))
	$events = unserialize(file_get_contents(__DIR__.'/../databases/fake_events.txt'));
else
	$events = array();

echo PMT."Enter event id:\n".RST;
$id = get_integer(array('min'=>0,'max'=>99999));

if (isset($events[$id])) {
	$time = get_datetime();
	$time->setTimezone(new DateTimeZone('UTC'));
	$events[$id]['begin_at'] = $time->format('Y-m-d\TH:i:s.\0\0\Z');
	$events[$id]['end_at'] = $time->format('Y-m-d\TH:i:s.\0\0\Z');
	file_put_contents(__DIR__.'/../databases/fake_events.txt', serialize($events));
}
else {
	echo ERR."Event $id not found.\n".RST;
}

?>
