#!/usr/bin/php
<?php
require_once(__DIR__ . '/src/Factory.php');

$scheduler = EventRemindersFactory::Create("INotificationScheduleHandler", true);
//$scheduler->schedule_notifications_all_event_users(3177);
//$scheduler->clear_all_scheduled_reminders();
//$scheduler->clear_scheduled_reminders_for_event(3177);
//$scheduler->schedule_notifications_all_events();
//$scheduler->clear_scheduled_reminders_for_user(28860);
//$scheduler->schedule_notifications_all_event_users(3177);
//$scheduler->clear_scheduled_reminders_for_user(54432);
//$scheduler->schedule_notifications_all_user_events(54432);
//$scheduler->clear_scheduled_reminder(49649, 3178, 1);
//$scheduler->clear_scheduled_reminders_for_user(51637);
//$scheduler->schedule_notifications(51637, 3177);

//$ready_reminders = $scheduler->get_ready_reminders();
//foreach ($ready_reminders as $reminder) {
//	echo "Scheduled time: " . $reminder['scheduled_time'] . " vs " . time() . "\n";
//}

//$ttnr = $scheduler->time_to_next_reminder();
//echo "Time until next reminder (seconds): $ttnr\n";
