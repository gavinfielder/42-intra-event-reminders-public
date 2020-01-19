#!/usr/bin/php
<?php

require_once(__DIR__.'/functions/input.php');

date_default_timezone_set('America/Tijuana');

echo PMT."Enter event id:\n".RST;
$id = get_integer(array('min'=>0,'max'=>99999));
$time = get_datetime();
echo PMT."Enter event name:\n".RST;
$name = get_input_nonempty();
echo PMT."Enter location:\n".RST;
$location = get_input_nonempty();
$time->setTimezone(new DateTimeZone('UTC'));

if (file_exists(__DIR__.'/../databases/fake_events.txt'))
	$events = unserialize(file_get_contents(__DIR__.'/../databases/fake_events.txt'));
else
	$events = array();

$events[$id] = array(
	'event_id' => $id,
	'location' => $location,
	'name' => $name,
	'begin_at' => $time->format('Y-m-d\TH:i:s\Z'),
	'end_at' => $time->format('Y-m-d\TH:i:s\Z'),
	'campus_id' => 7
);

file_put_contents(__DIR__.'/../databases/fake_events.txt', serialize($events));

?>
