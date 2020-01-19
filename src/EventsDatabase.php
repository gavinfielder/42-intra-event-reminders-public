<?php

require_once(__DIR__ . '/../settings.php');
require_once(__DIR__."/logging.php");
require_once(__DIR__."/error.php");
require_once(__DIR__."/IEventsDataHandler.php");
require_once(__DIR__."/IFtApiHandler.php");
require_once(__DIR__."/INotificationScheduleHandler.php");
require_once(__DIR__."/DatabaseManager.php");

/**
 * This file holds the EventsDatabase handler class
 *
 * Below the class there are also general functions:
 *
 *     events_array_contains($arr, $event)
 *
 */

/**
 * TODO: documentation needs updating
 *
 * EventsDatabase handles the 'events' data. It keeps a working copy
 * of the events table in working memory to improve processing speed
 * while working with the data.
 *
 *   Methods:
 *     access_events()              returns the events table
 *     insert_event(event)          inserts an event
 *     remove_outdated_events()     removes and returns number removed from working copy
 *     save_events()                saves changes to the database
 *     discard_data()               discards the working copy
 *     release_data()               saves and removes the working copy
 *     dump_data_to_file(filename)  dump the working copy to file
 *
 *   Unless otherwise specified, all methods return true on success
 *   and false on failure.
 *
 * Note: on deconstruction, any unsaved changes will be automatically
 * saved, but it is recommended to explicitly call either
 * discard_data or release_data when done using the database. Doing
 * so will ensure that the next time the data is needed, it will be
 * read from the database itself.
 *
 */
class   EventsDatabase extends SQLite3 implements IEventsDataHandler {
    const DB_PATH = __DIR__ . "/../databases/";
    const DB_FILE = "events.db";

    private $utc_timezone;
    private $events_table;
    private $data_changed;

    //Dependencies
    private $api; //IFtApiHandler

    /**
     * Constructor
     */
    function __construct(IFtApiHandler $apih) {
        $this->utc_timezone = new DateTimeZone('UTC');
        $this->events_table = null;
        $this->data_changed = false;
        $this->api = $apih;
        $this->initialize();
    }

    /**
     * Destructor
     */
    function __destruct() {
        if ($this->data_changed) {
            if ($this->save_events() === false) {
                cli_error_log("EventsDatabase: failed saving events at destruction. Dumping data to temporary file.");
                $this->dump_data_to_file("events-table-save-fail-data-dump.tmp");
            }
            else {
                cli_log("EventsDatabase: data saved at destruction.");
            }
        }
        $this->close();
    }

    /**
     * Opens or creates database file if it doesn't exist,
     */
    private function initialize() {
        $new_db = false;
        if (!file_exists(self::DB_PATH)) {
            $new_db = true;
            if (!mkdir(self::DB_PATH, 0777, true)) {
                cli_error("EventsDatabase: Fatal: Unable to create database directory.", true);
            }
        }
        try {
            if (!file_exists(self::DB_PATH . self::DB_FILE))
                $new_db = true;
            open_database($this, self::DB_PATH . self::DB_FILE);
        }
        catch (Exception $e) {
                cli_error("EventsDatabase: Fatal: Unable to open events.db.", true);
        }
        if ($new_db)
            $this->make_table();
    }

    /**
     * Discards the saved data without updating the database
     */
    function    discard_data() {
        $this->events_table = null;
        $this->data_changed = false;
        return (true);
    }

    /**
     * Removes the data from memory
     */
    function    release_data(bool $discard_changes = false) {
        $success = true;
        if (!$discard_changes && $this->data_changed) {
            if ($this->save_events() === false) {
                cli_error_log("EventsDatabase: could not save data on release. Dumping to file.");
                $this->dump_data_to_file("events-table-release-failed-data-dump.tmp");
                $success = false;
            }
        }
        if ($this->discard_data() === false)
            $success = false;
        return ($success);
    }

    /**
     * part of IEventsDataHandler
     */
    function    release_cache() {
        $this->release_data(true);
    }

    /**
     * Dumps the saved data to file
     */
    function    dump_data_to_file($filename) {
        $fout = fopen($filename, "w");
        if ($fout) {
            fwrite($fout, print_r($this->events_table, true));
            fclose($fout);
            return (true);
        }
        cli_error_log("EventsDatabase: error during data file dump");
        return (false);
    }

    /**
     * Creates the events table if it does not exist
     */
    private function make_table() {
        $result = $this->query(
            "CREATE TABLE IF NOT EXISTS events(
                data BLOB
            );"
        );
        if ($result === false) {
            cli_error_log("EventsDatabase: could not make table. Reason: "
                .$this->lastErrorMsg());
        }
    }

    /**
     * Returns the events table, fetching it if needed
     */
    function    access_events() {
        if ($this->events_table)
            return ($this->events_table);
        else {
            $result = $this->query("SELECT data FROM events");
            if ($result === false) {
                cli_error_log("EventsDatabase: could not access events. Reason: "
                    .$this->lastErrorMsg());
                return (false);
            }
            $tmp = $result->fetchArray();
            $str = null;
            if ($tmp === false)
                ;//no records
            else if (!$tmp)
                cli_error_log("EventsDatabase: access_events: could not fetch query results.");
            else
                $str = $tmp['data'];
            if ($str)
                $this->events_table = json_decode($str, true);
            if (!($this->events_table)) {
                $this->events_table = array();
                cli_error_log("EventsDatabase: access_events: Warning: No event data detected.");
            }
            $this->data_changed = false;
            return ($this->events_table);
        }
    }

    /**
     * Saves the events table. Returns true on success, false on failure
     */
    function    save_events() {
        if (!($this->events_table)) {
            cli_error_log("EventsDatabase: could not save events table: no data loaded");
            return (false);
        }
        if (($result = $this->query("DELETE FROM events")) === false) {
            cli_error_log("EventsDatabase: could not save events table (delete step): Reason: "
                .$this->lastErrorMsg());
            return (false);
        }
        $ins = $this->prepare("INSERT INTO events(data) VALUES (:events_table);");
        $json = json_encode($this->events_table);
        $ins->bindValue(':events_table', $json, SQLITE3_BLOB);
        $result = $ins->execute();
        if ($result === false) {
            cli_error_log("EventsDatabase: could not save events table (insert step): Reason: "
                .$this->lastErrorMsg());
            return (false);
        }
        //now in sync with the database, so set data_changed to false
        $this->data_changed = false;
        return (true);
    }

    /**
     * Inserts an event. Return true on success, false on failure
     * Note this does not immediately save the data to the database
     */
    function    insert_event($event) {
        if ($this->access_events() === false) {
            cli_error_log("EventsDatabase: Could not insert events. Reason: access_events failed");
            return (false);
        }
        //validate input
        if (!isset($event['event_id']) || !isset($event['location']) ||
            !isset($event['name']) || !isset($event['begin_at']) ||
            !isset($event['end_at']) || !isset($event['campus_id']))
        {
            cli_error_log('EventsDatabase: could not insert event: invalid input');
            return (false);
        }
        //If users is not given, set to empty array
        if (!isset($event['users']))
            $event['users'] = array();
        //Insert event
        $this->events_table[$event['event_id']] = $event;
        $this->data_changed = true;
        return (true);
    }

    /**
     * Removes outdated events from the saved data
     * Note this does not immediately save the data to the database
     *
     * On success, returns the number of events removed
     */
    function    remove_outdated_events() {
        if ($this->access_events() === false) {
            cli_error_log("EventsDatabase: Could not remove outdated events: access_events failed");
            return (false);
        }
        $now = new DateTime('NOW', $this->utc_timezone);
        $fmt = 'Y-m-d\TH:i:s.u\Z';
        $num_removed = 0;
        foreach ($this->events_table as $key => $event) {
            $time = DateTime::createFromFormat($fmt, $event['begin_at'], $this->utc_timezone);
            if ($time === false) {
                cli_error_log("EventsDatabase: could not parse datetime for comparison."
                    ."\n    fmt=\"$fmt\"\n    begin_at=\"".$event['begin_at']."\"");
            }
            else {
                if ($time < $now) {
                    unset($this->events_table[$key]);
                    $this->data_changed = true;
                    $num_removed++;
                }
            }
        }
        return ($num_removed);
    }

    /**
     * Adds a user to an event
     * Note this does not immediately save the data to the database
     */
    function    add_user_to_event($event_id, $user) {
        if (!is_array($user) || !isset($user['user_id'])) {
            cli_error_log("EventsDatabase: could not add user to event: invalid input");
            return (false);
        }
        if ($this->access_events() === false) {
            cli_error_log("EventsDatabase: Could not add user to event: access_events failed");
            return (false);
        }
        if (!array_key_exists($event_id, $this->events_table)) {
            cli_error_log("EventsDatabase: Could not add user to event: event not found");
            return (false);
        }
        $this->events_table[$event_id]['users'][$user['user_id']] = $user;
        $this->data_changed = true;
        return (true);
    }

    /**
     * Updates the the events table
     */
    function    update_events($sch) {
        try {
            if (($events = $this->access_events()) === false) {
                cli_error_log("EventsDatabase: Could not update events: could not access events table");
                return (false);
            }
            //Keep in mind $events is a deep copy of $this->events_table
            $api_results = $this->api->access_upcoming_events();
            if ($api_results === null) {
                cli_error_log("EventsDatabase: Could not update events: api call failed");
                return (false);
            }
            $num_new = 0;
            $num_processed = 0;
            $new = array();
            foreach ($api_results as $key => $event) {
                $event_id = $event['event_id'];
                if (array_key_exists($event_id, $events)) {
                    if ($event['begin_at'] != $events[$event_id]['begin_at']) {
                        $old_time = $events[$event_id]['begin_at'];
                        $this->events_table[$event_id]['begin_at'] = $event['begin_at'];
                        $this->events_table[$event_id]['end_at'] = $event['end_at'];
                        $new_time = $event['begin_at'];
                        $this->data_changed = true;
                        $this->save_events();
                        cli_log("EventsDatabase: Event $event_id time changed from $old_time to $new_time.");
                        if ($sch instanceof INotificationScheduleHandler) {
                            $sch->clear_scheduled_reminders_for_event($event_id);
                            $sch->schedule_notifications_all_event_users($event_id);
                            //TODO: check for success of the above
                            cli_log("EventsDatabase: Rescheduled notifications for event $event_id.");
                        }
                        else {
                            cli_error_log("EventsDatabase: Warning: Did not reschedule notifications for $event_id.");
                        }
                    }
                }
                else {
                    $this->insert_event($event);
                    $new[$event['event_id']] = $event;
                    $num_new++;
                }
                $num_processed++;
            }
            if (DEBUG && $num_new > 0) {
                cli_log("EventsDatabase: $num_processed events upcoming. Inserted $num_new new events:\n"
                    .print_r($new, true));
            }
            else if (DEBUG)
                cli_log("EventsDatabase: No new events found. $num_processed events upcoming.");
            $num_removed = $this->remove_outdated_events();
            if (DEBUG) cli_log("EventsDatabase: $num_removed outdated events removed.");
            $this->save_events();
            return ($num_new);
        }
        catch (Exception $ex) {
            cli_error_log("EventsDatabase: Could not update events. Exception Message: "
                .$ex->getMessage());
            return (false);
        }
    }

    /**
     * Updates the event users for all the events in the events table
     *
     * sch is either an instance of INotificationScheduleHandler or null
     */
    function    update_event_users($sch) {
        try {
            if (($events = $this->access_events()) === false) {
                cli_error_log("EventsDatabase: Could not update event users: could not access events table");
                return (false);
            }
            $num_events_updated = 0;
            $num_users_added = 0;
            foreach ($events as $event_id => $event) {
                $api_results = $this->api->access_event_users($event_id);
                if ($api_results === null) {
                    cli_error_log("EventsDatabase: Could not update events users for event $event_id: api call failed");
                }
                if (is_array($api_results)) {
                    $tmp = 0;
                    foreach ($api_results as $user_id => $user) {
                        if (!array_key_exists($user_id, $events[$event_id]['users'])) {
                            $this->add_user_to_event($event_id, $user);
                            if (DEBUG) cli_log("EventsDatabase: User $user_id (".$user['login'].") added to event $event_id.");
                            if ($sch instanceof INotificationScheduleHandler) {
                                $sch->schedule_notifications($user_id, $event_id);
                                cli_log("EventsDatabase: scheduled notifications for user $user_id and event $event_id.");
                            }
                            $tmp++;
                            $num_users_added++;
                        }
                    }
                    if ($tmp > 0)
                        $num_events_updated++;
                }
                else {
                    cli_error_log("EventsDatabase: could not retrieve event users from api for event $event_id");
                }
            }
            
            //save any changes
            if ($this->save_events()) {
                if (DEBUG) cli_log("EventsDatabase: $num_users_added total users added to $num_events_updated events.");
                return ($num_users_added);
            }
            else
                cli_error_log("EventsDatabase: Could not update events users: could not save data.");
            return (false);
        }
        catch (Exception $ex) {
            cli_error_log("EventsDatabase: Could not update event users. Exception Message: "
                .$ex->getMessage());
            return (false);
        }
    }
}

function    events_array_contains($arr, $event) {
    if (!(isset($event['event_id'])) || !is_array($arr)) {
        cli_error_log("events_array_contains: invalid input");
        return (null);
    }
    if (array_key_exists($event['event_id'], $arr))
        return (true);
    return (false);
}

?>
