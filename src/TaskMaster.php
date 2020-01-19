<?php

require_once(__DIR__.'/../settings.php');
require_once(__DIR__.'/error.php');
require_once(__DIR__.'/logging.php');
require_once(__DIR__.'/ITaskMaster.php');

class TaskMaster implements ITaskMaster {

    //Task scheduling data
    private $updated_event_users_time;
    private $updated_events_time;
    private $updated_slack_directory_time;
    private $refreshed_authentication_time;
    private $task_frequencies;

    //Dependencies
    private $api;      //IFtApiHandler
    private $cdh;      //ICampusesDataHandler
    private $nsh;      //INotificationScheduleHandler
    private $auth;     //IAuthenticationHandler
    private $edh;      //IEventsDataHandler
    private $sets;     //IEventReminderSettingsHandler
    private $slack;    //array of ISlackHandler indexed by campus_id
    private $email;    //IEmailHandler
    private $sms;      //ISMSHandler
    private $whatsapp; //IWhatsAppHandler

    /**
     * Updates campuses data first and then updates events
     * (Completes quickly)
     */
    public function update_events() {
        $start_time = time();
        if ($this->update_campuses() === false) {
            cli_error_log("TaskMaster: update_events failed at updating campuses step.");
            $num_new_events = false;
        }
        else {
            $num_new_events = $this->edh->update_events($this->nsh);
            if ($num_new_events === false) {
                cli_error_log("TaskMaster: update_events failed at updating events step.");
            }
            else {
                $this->updated_events_time = $start_time;
                $this->save_updated_events_time();
            }
        }
        $duration = time() - $start_time;
        return (array(
            'seconds_taken' => $duration,
            'n' => $num_new_events,
            'n_name' => 'Inserted',
            'success' => ($num_new_events === false ? false : true)
        ));
    }

    /**
     * Updates campuses data
     * (Completes quickly)
     */
    public function update_campuses() {
        $start_time = time();
        $result = $this->cdh->update_campuses($this->api);
        $duration = time() - $start_time;
        return (array(
            'seconds_taken' => $duration,
            'success' => ($result === false ? false : true)
        ));
    }

    /**
     * Refreshes the server's 42 API Authentication
     * (Completes quickly)
     */
    public function refresh_authentication() {
        $start_time = time();
        $ret = $this->auth->refresh_access();
        if ($ret !== false)
            $this->refreshed_authentication_time = $start_time;
        $duration = time() - $start_time;
        return (array(
            'seconds_taken' => $duration,
            'success' => ($ret === false ? false : true)
        ));
    }

    /**
     * Updates event users: which users are registered to the upcoming events
     * (20-30 seconds on average)
     */
    public function update_event_users() {
        $start_time = time();
        $num_new_event_users = $this->edh->update_event_users($this->nsh);
        if ($num_new_event_users !== false) {
            $this->updated_event_users_time = $start_time;
        }
        else
            cli_error_log("TaskMaster: update_event_users failed.");
        $duration = time() - $start_time;
        return (array(
            'seconds_taken' => $duration,
            'n' => $num_new_event_users,
            'n_name' => 'Inserted',
            'success' => ($num_new_event_users === false ? false : true)
        ));
    }

    /**
     * Updates the slack directory
     * (~3 minutes or more)
     */
    public function update_slack_directory(int $campus_id) {
        $start_time = time();
        $result = false;
        if (isset($this->slack[$campus_id])) {
            $result = $this->slack[$campus_id]->update_slack_data();
            if ($result !== false) {
                $this->updated_slack_directory_time[$campus_id] = time();
            $this->save_updated_slack_directory_time($campus_id);
            }
            else {
                cli_error_log("TaskMaster: update_slack_directory($campus_id) failed.");
            }
        }
        else {
            cli_error_log("TaskMaster: update_slack_directory($campus_id): no slack handler for campus $campus_id.");
        }
        $duration = time() - $start_time;
        //cli_log("TaskMaster: Updated slack directory in $duration seconds.");
        return (array(
            'seconds_taken' => $duration,
            'success' => ($result === false ? false : true)
        ));
    }

    /**
     * Returns the time in seconds to the next scheduled notification
     */
    public function time_to_next_notification() {
        return ($this->nsh->time_to_next_reminder());
    }

    /**
     * Handles all the notifications that are ready to go out
     */
    public function handle_notifications() {
        $start_time = time();
        $reminders = $this->nsh->get_ready_reminders();
        $num_sent = 0;
        if (is_array($reminders)) {
            //Ensure api data is fetched from the source when we call verify_reminder_list
            $this->api->clear_cached_data('events');
            $this->api->clear_cached_data('event_users');
            $this->nsh->verify_reminder_list($reminders);
            foreach ($reminders as $key => $rem) {
                $i = $rem['reminder_id'];
                $settings = $this->sets->get_user_settings($rem['user_id']);
                if (isset($settings['reminder_times_json']["reminder-time-$i-platform"]))
                    $method = $settings['reminder_times_json']["reminder-time-$i-platform"];
                else
                    $method = "error_method_not_set";
                if ($rem['valid']) {
                    $user = $this->api->access_user($rem['user_id']);
                    $event = $this->api->access_event($rem['event_id']);
                    $settings = $this->sets->get_user_settings($rem['user_id']);
                    if (isset($settings['reminder_times_json']["reminder-time-$i-platform"]))
                        $method = $settings['reminder_times_json']["reminder-time-$i-platform"];
                    else
                        $method = "error_method_not_set";
                    switch ($method) {
                    case 'email':
                        $success = $this->email->email_event_reminder($user, $event, $this->sets);
                        break;
                    case 'slack':
                        if (isset($this->slack[$event['campus_id']])) {
                            $success = $this->slack[$event['campus_id']]->send_slack_reminder(
                                $user, $event, $this->sets);
                        }
                        else {
                            $success = 'false';
                            cli_error_log("TaskMaster: Campus ".$event['campus_id']." has no SlackHandler.");
                        }
                        break;
                    case 'sms':
                        $success = $this->sms->send_sms_reminder($user, $event, $this->sets);
                        break;
                    case 'whatsapp':
                        $success = $this->whatsapp->send_whatsapp_reminder($user, $event, $this->sets);
                        break;
                    default:
                        cli_error_log("TaskMaster: Unknown method $method");
                        $success = false;
                    }
                    if ($success) {
                        cli_log("TaskMaster: Sent $method to user ".$user['user_id']
                            ." (".$user['login'].") regarding event "
                            .$event['event_id']." (".$event['name'].")");
                        $this->nsh->clear_scheduled_reminder($rem['user_id'], $rem['event_id'], $i);
                        cli_log("TaskMaster: called clear_scheduled_reminder(".$rem['user_id'].", ".$rem['event_id'].", $i)");
                        $num_sent++;
                    }
                    else {
                        cli_error_log("TaskMaster: Could not send $method to user ".$user['user_id']
                            ." (".$user['login'].") regarding event "
                            .$event['event_id']." (".$event['name'].")");
                    }
                    unset($user);
                    unset($event);
                }
                else {
                    $this->nsh->clear_scheduled_reminder($rem['user_id'], $rem['event_id'], $i);
                    cli_error_log("TaskMaster: Removed Invalid Task: Send $method to user "
                        .$rem['user_id']
                        .(isset($user) ? " (".$user['login'].") " : "")." regarding event "
                        .$rem['event_id']
                        .(isset($event) ? " (".$event['name'].")." : "."));
                }
            }
        }
        else {
            cli_error_log("TaskMaster: handle_notifications: nsh->get_ready_reminders failed.");
        }
        $duration = time() - $start_time;
        return (array(
            'seconds_taken' => $duration,
            'n_name' => 'Sent',
            'n' => $num_sent
        ));
    }

    /**
     * Determines which task should be run next and runs it
     *
     * Returns an associative array with at least the following keys:
     *    'task_name'
     *    'seconds_taken'
     *    'forced'
     */
    public function run_next_task() {
        $now = time();
        //Check for severely outdated tasks (['high'])
        //Lowest dependencies first
        if ($now - $this->refreshed_authentication_time > 
                $this->task_frequencies['refresh_authentication']['high']) {
            return ($this->format_task_result(
                'refresh_authentication', 
                $this->refresh_authentication(),
                true
            ));
        }
        if ($now - $this->updated_events_time >
                $this->task_frequencies['update_events']['high']) {
            return ($this->format_task_result(
                'update_events',
                $this->update_events($this->nsh),
                true
            ));
        }
        if ($now - $this->updated_event_users_time > 
                $this->task_frequencies['update_event_users']['high']) {
            return ($this->format_task_result(
                'update_event_users', 
                $this->update_event_users(),
                true
            ));
        }
        foreach ($this->updated_slack_directory_time as $campus_id => $updated_time) {
            if ($now - $updated_time > 
                    $this->task_frequencies['update_slack_directory']['high']) {
                return ($this->format_task_result(
                    "update_slack_directory (campus_id: $campus_id)",
                    $this->update_slack_directory($campus_id),
                    true
                ));
            }
        }
        //Check for outdated tasks that can be run now
        //without running into the need to send notifications
        //Highest priority first
        $notif = $this->time_to_next_notification();
        if (DEBUG) cli_log("TaskMaster: $notif seconds to next scheduled notification.");
        if ($notif > 0) {
            if ($now - $this->refreshed_authentication_time >
                    $this->task_frequencies['refresh_authentication']['low']
                    && $notif > 
                    $this->task_frequencies['refresh_authentication']['time_needed']) {
                return ($this->format_task_result(
                        'refresh_authentication',
                        $this->refresh_authentication(),
                        false
                ));
            }
            if ($now - $this->updated_event_users_time >
                    $this->task_frequencies['update_event_users']['low']
                    && $notif > 
                    $this->task_frequencies['update_event_users']['time_needed']) {
                return ($this->format_task_result(
                        'update_event_users',
                        $this->update_event_users(),
                        false
                ));
            }
            foreach ($this->updated_slack_directory_time as $campus_id => $updated_time) {
                if ($now - $updated_time >
                        $this->task_frequencies['update_slack_directory']['low']
                        && $notif > 
                        $this->task_frequencies['update_slack_directory']['time_needed']) {
                    return ($this->format_task_result(
                            'update_slack_directory',
                            $this->update_slack_directory($campus_id),
                            false
                        ));
                }
            }
            if ($now - $this->updated_events_time >
                    $this->task_frequencies['update_events']['low']
                    && $notif > 
                    $this->task_frequencies['update_events']['time_needed']) {
                return ($this->format_task_result(
                        'update_events',
                        $this->update_events($this->nsh),
                        false
                ));
            }
        }
        //If no other tasks were run, handle notifications
        return ($this->format_task_result(
            'handle_notifications',
            $this->handle_notifications(),
            false
        ));
    }

    private function format_task_result($task_name, $task_result, $forced) {
        return (array(
            'task_name' => $task_name,
            'forced' => $forced
        ) + $task_result);
    }

    /**
     * Constructor
     * (meant to be constructed by Factory)
     */
    function        __construct(
                        IFtApiHandler $apih,
                        ICampusesDataHandler $cdhh,
                        INotificationScheduleHandler $nshh,
                        IAuthenticationHandler $authh,
                        IEventsDataHandler $edhh,
                        IEmailHandler $emailh,
                        array $slack_handlers,
                        ISMSHandler $smsh,
                        IWhatsAppHandler $whatsapph,
                        IEventReminderSettingsHandler $setsh
                    ) {
        //Set dependencies
        $this->api = $apih;
        $this->cdh = $cdhh;
        $this->nsh = $nshh;
        $this->auth = $authh;
        $this->edh = $edhh;
        $this->slack = $slack_handlers;
        $this->email = $emailh;
        $this->sms = $smsh;
        $this->whatsapp = $whatsapph;
        $this->sets = $setsh;

        //Set task schedule frequencies
        $this->task_frequencies = array();
        $this->task_frequencies['update_events'] = array();
        $this->task_frequencies['update_events']['low'] = MINIMUM_SECONDS_BETWEEN_UPDATING_EVENTS;
        $this->task_frequencies['update_events']['high'] = MAXIMUM_SECONDS_BETWEEN_UPDATING_EVENTS;
        $this->task_frequencies['update_events']['time_needed'] = SECONDS_NEEDED_TO_UPDATE_EVENTS;
        $this->task_frequencies['refresh_authentication'] = array();
        $this->task_frequencies['refresh_authentication']['low'] = MINIMUM_SECONDS_BETWEEN_REFRESHING_AUTHENTICATION;
        $this->task_frequencies['refresh_authentication']['high'] = MAXIMUM_SECONDS_BETWEEN_REFRESHING_AUTHENTICATION;
        $this->task_frequencies['refresh_authentication']['time_needed'] = SECONDS_NEEDED_TO_REFRESH_AUTHENTICATION;
        $this->task_frequencies['update_event_users'] = array();
        $this->task_frequencies['update_event_users']['low'] = MINIMUM_SECONDS_BETWEEN_UPDATING_EVENT_USERS;
        $this->task_frequencies['update_event_users']['high'] = MAXIMUM_SECONDS_BETWEEN_UPDATING_EVENT_USERS;
        $this->task_frequencies['update_event_users']['time_needed'] = SECONDS_NEEDED_TO_UPDATE_EVENT_USERS;
        $this->task_frequencies['update_slack_directory'] = array();
        $this->task_frequencies['update_slack_directory']['low'] = MINIMUM_SECONDS_BETWEEN_UPDATING_SLACK_DIRECTORY;
        $this->task_frequencies['update_slack_directory']['high'] = MAXIMUM_SECONDS_BETWEEN_UPDATING_SLACK_DIRECTORY;
        $this->task_frequencies['update_slack_directory']['time_needed'] = SECONDS_NEEDED_TO_UPDATE_SLACK_DIRECTORY;

        //Set initial data
        $this->updated_event_users_time = 0;
        $this->refreshed_authentication_time = 0;
        $this->load_updated_events_time();
        $this->updated_slack_directory_time = array();
        foreach ($this->slack as $campus_id => $handler) {
            $this->load_updated_slack_directory_time($campus_id);
        }
    }

    private function load_updated_events_time() {
        $path = __DIR__.'/../databases/updated_events_time.txt';
        if (file_exists($path)) {
            try {
                $data = unserialize(file_get_contents($path));
                $this->updated_events_time = $data;
                return (true);
            }
            catch (Exception $ex) {
                cli_error_log("TaskMaster: could not load updated events time. "
                    ."Exception Message: ".$ex->getMessage());
            }
        }
        $this->updated_events_time = 0;
        return (false);
    }

    private function save_updated_events_time() {
        $dir = __DIR__.'/../databases';
        $file = '/updated_events_time.txt';
        if (!file_exists($dir))
            mkdir($dir);
        if (file_exists($dir)) {
            try {
                file_put_contents($dir.$file, serialize($this->updated_events_time));
            }
            catch (Exception $ex) {
                    cli_error_log("TaskMaster: could not save updated event users time. "
                    ."Exception Message: ".$ex->getMessage());          
            }
        }
    }

    private function load_updated_slack_directory_time(int $campus_id) {
        $path = __DIR__."/../databases/updated_slack_directory_time_$campus_id.txt";
        if (file_exists($path)) {
            try {
                $data = unserialize(file_get_contents($path));
                $this->updated_slack_directory_time[$campus_id] = $data;
                return (true);
            }
            catch (Exception $ex) {
                cli_error_log("TaskMaster: could not load updated slack directory time. "
                    ."Exception Message: ".$ex->getMessage());
            }
        }
        $this->updated_slack_directory_time[$campus_id] = 0;
        return (false);
    }

    private function save_updated_slack_directory_time(int $campus_id) {
        $dir = __DIR__.'/../databases';
        $file = "/updated_slack_directory_time_$campus_id.txt";
        if (!file_exists($dir))
            mkdir($dir);
        if (file_exists($dir)) {
            try {
                file_put_contents($dir.$file, serialize($this->updated_slack_directory_time[$campus_id]));
            }
            catch (Exception $ex) {
                    cli_error_log("TaskMaster: could not save updated slack directory time. "
                    ."Exception Message: ".$ex->getMessage());          
            }
        }
    }
}

?>
