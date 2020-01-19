<?php

require_once(__DIR__.'/../settings.php');
require_once(__DIR__.'/error.php');
require_once(__DIR__.'/logging.php');
require_once(__DIR__.'/IFtApiHandler.php');

class FakeFtApiHandler implements IFtApiHandler {
	public $cache;

	/**
	 * Constructor
	 */
	function __construct() {
		$this->clear_cached_data();
	}

	/**
	 * Resets the data with the current values from file
	 */
	function	clear_cached_data($only_key = null) {
		$now = time();
		if (file_exists(__DIR__.'/../databases/enabled_fake_events.dat'))
			$enabled_events = file_get_contents(__DIR__.'/../databases/enabled_fake_events.dat');
		else
			$enabled_events = "";
		if (file_exists(__DIR__.'/../databases/fake_events.txt'))
			$this->cache['events'] = unserialize(file_get_contents(__DIR__.'/../databases/fake_events.txt'));
		else
			$this->cache['events'] = array();
		/*
		$tmp = $this->cache['events'];
		foreach ($this->cache['events'] as $key => $event) {
			if (strpos($enabled_events, "$key") === false)
				unset($tmp[$key]);
		}
		$this->cache['events'] = $tmp;
		 */
		if (file_exists(__DIR__.'/../databases/fake_users.txt'))
			$this->cache['users'] = unserialize(file_get_contents(__DIR__.'/../databases/fake_users.txt'));
		else
			$this->cache['users'] = array();
		if (file_exists(__DIR__.'/../databases/fake_user_events.txt'))
			$this->cache['user_events'] = unserialize(file_get_contents(__DIR__.'/../databases/fake_user_events.txt'));
		else
			$this->cache['user_events'] = array();
		$this->cache['upcoming_events'] = $this->cache['events'];
		$tmp = $this->cache['upcoming_events'];
		foreach ($this->cache['upcoming_events']  as $key => $event) {
			if (strtotime($event['begin_at']) <= $now)
				unset($tmp[$key]);
		}
		$this->cache['upcoming_events'] = $tmp;
		if (file_exists(__DIR__.'/../databases/fake_event_users.txt'))
			$this->cache['event_users'] = unserialize(file_get_contents(__DIR__.'/../databases/fake_event_users.txt'));
		else
			$this->cache['event_users'] = array();
	}

	/**
	 * only here to satisfy the interface
	 */
	public function set_token($access_token) { }

	/**
	 * only here to satisfy the interface
	 */
	public function get_current_token() {
		return (array('access_token' => "peekaboo"));
	}

	/**
	 * The general access point for api requests
	 *
	 * Will return campus data to the campus data handler but that's it
	 */
	function	get($endpoint, array $params) {
		if ($endpoint == '/v2/campus') {
			return (array(
				'7' => array(
					'id' => 7,
					'name' => 'Fremont',
					'time_zone' => 'America/Tijuana',
					'language' => array('identifier' => 'en'),
					'country' => 'United States',
					'city' => 'Fremont',
					'address' => '6 600 Dumbarton Circle',
					'zip' => '94555'
				)
			));
		}
		cli_error_log("FakeFtApiHandler: IFtApiHandler::get is not supported when using the fake api. You attempted endpoint \"$endpoint\" with parameters:\n".print_r($params, true));
		return (null);
	}

	/**
	 * Gets up to 100 events that start between now and a week from now,
	 * prioritized by starting soonest
	 */
	function	get_upcoming_events() {
		$this->clear_cached_data();
		return ($this->cache['upcoming_events']);
	}
	
	/**
	 * Gets event details
	 */
	function	get_event($event_id) {
		$this->clear_cached_data();
		if (isset($this->cache['events'][$event_id]))
			return ($this->cache['events'][$event_id]);
		return (null);
	}

	/**
	 * Gets details of current user
	 *
	 * Sets cache['me']
	 */
	function	get_me() {
		cli_error_log("FakeFtApiHandler: IFtApiHandler::me is not supported when using the fake api.");
		return (null);
	}

	/**
	 * Gets the requested user
	 *
	 * Sets cache['users'][user_id]
	 */
	function	get_user($user_id) {
		$this->clear_cached_data();
		if (isset($this->cache['users'][$user_id]))
			return ($this->cache['users'][$user_id]);
		return (null);
	}

	/**
	 * Gets the requested event users
	 *
	 * Does not set email, first_name, or last_name
	 *
	 * Sets cache['event_users'][event_id]
	 */
	function	get_event_users($event_id) {
		$this->clear_cached_data();
		if (isset($this->cache['event_users'][$event_id]))
			return ($this->cache['event_users'][$event_id]);
		return (null);
	}

	/**
	 * Gets all events the given user is registered to
	 *
	 * Sets cache['user_events'][user_id]
	 */
	function	get_user_events($user_id) {
		$this->clear_cached_data($user_id);
		if (isset($this->cache['user_events'][$user_id]))
			return ($this->cache['user_events'][$user_id]);
		return (null);
	}

	/**
	 * Access functions check the relevant cache first, and then call
	 * the associated get_ function if needed
	 */
	function	access_event($event_id) {
		if (isset($this->cache['events'][$event_id]))
			return ($this->cache['events'][$event_id]);
		return ($this->get_event($event_id));
	}
	function	access_upcoming_events() {
		if (isset($this->cache['upcoming_events']))
			return ($this->cache['upcoming_events']);
		return ($this->get_upcoming_events());
	}
	function	access_me() {
		if (isset($this->cache['me']))
			return ($this->cache['me']);
		return ($this->get_me());
	}
	function	access_user($user_id) {
		if (isset($this->cache['users'][$user_id]))
			return ($this->cache['users'][$user_id]);
		return ($this->get_user($user_id));
	}
	function	access_event_users($event_id) {
		if (isset($this->cache['event_users'][$event_id]))
			return ($this->cache['event_users'][$event_id]);
		return ($this->get_event_users($event_id));
	}
	function	access_user_events($user_id) {
		if (isset($this->cache['user_events'][$user_id]))
			return ($this->cache['user_events'][$user_id]);
		return ($this->get_user_events($user_id));
	}
}

?>
