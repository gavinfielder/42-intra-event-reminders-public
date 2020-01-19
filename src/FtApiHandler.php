<?php
require_once(__DIR__ . '/../settings.php');
require_once(__DIR__."/logging.php");
require_once(__DIR__."/error.php");
require_once(__DIR__.'/IFtApiHandler.php');
require_once(__DIR__.'/auth.php'); //for checking token validity

class FtApiHandler implements IFtApiHandler {
	private $token;
	private $cache;

	/**
	 * Constructor
	 */
	function __construct($access_token) {
		$this->set_token($access_token);
		$this->clear_cached_data(); //initializes cache
	}

	/**
	 * Clears cached data
	 */
	function	clear_cached_data($only_key = null) {
		$this->cache = array();
		if (!$only_key || $only_key == 'events') $this->cache['events'] = array();
		if (!$only_key || $only_key == 'event_users') $this->cache['event_users'] = array();
		if (!$only_key || $only_key == 'users') $this->cache['users'] = array();
		if (!$only_key || $only_key == 'user_events') $this->cache['user_events'] = array();
		if (!$only_key || $only_key == 'upcoming_events') $this->cache['upcoming_events'] = null;
		if (!$only_key || $only_key == 'me') $this->cache['me'] = null;
	}

	/**
	 * Sets the access token
	 * Returns true if set, false if failure.
	 * Will output a warning message if the token is invalid
	 */
	public function set_token($access_token) {
		if (is_array($access_token)) {
			if (isset($access_token['access_token'])) {
				$this->token = $access_token['access_token'];
				if (check_token_validity($access_token) !== true) //TODO: check_token_validity is legacy code
					contextual_error_log('FtApiHandler: Warning: token is not valid.');
				return (true);
			}
			else {
				contextual_error_log('FtApiHandler: set_token failed: no access token found in array');
				return (false);
			}
		}
		else {
			$this->token = $access_token;
			if (check_token_validity($access_token) !== true)
				contextual_error_log('FtApiHandler: Warning: token is not valid.');
			return (true);
		}
	}

	/**
	 * Accesses the current token
	 */
	public function get_current_token() {
		return ($this->token);
	}

	/**
	 * The general access point for api requests
	 *
	 * Returns array result if successful, null on failure
	 *
	 * Does not set any cache data.
	 */
	function	get($endpoint, array $params) {
		try {
			$url = 'https://api.intra.42.fr'.$endpoint;
			$query = http_build_query($params);
			$options = array('http' => array(
				'header' => "Content-type: application/x-www-form-urlencoded\r\n".
							"Content-Length: ".strlen($query)."\r\n".
							"Authorization: Bearer ".$this->token."\r\n",
				'method' => 'GET',
				'content' => $query
			));
			$context = stream_context_create($options);
			$result = file_get_contents($url, false, $context);
			if ($result === false) {
				contextual_error_log('FtApiHandler: API call failed');
				return (null);
			}
			$arr = json_decode(trim($result), true);
			return ($arr);
		}
		catch (Exception $ex) {
			contextual_error_log('FtApiHandler: API call failed. Exception message: '
				.$ex->getMessage());
			return (null);
		}
	}

	/**
	 * Gets up to 100 events that start between now and a week from now,
	 * prioritized by starting soonest
	 *
	 * Sets cache['upcoming_events']
	 */
	function	get_upcoming_events() {
		try {
			$now = new DateTime('NOW', new DateTimeZone('UTC'));
			$next_week = new DateTime('NOW', new DateTimeZone('UTC')); $next_week->modify('+1 week');
			$datetime_fmt = 'Y-m-d\TH:i:s\Z';
			$events = $this->get(
				'/v2/events',
				array(
					'range[begin_at]' => ''.$now->format($datetime_fmt).','
											.$next_week->format($datetime_fmt),
					'sort' => 'begin_at,-id',
					'page[size]' => '100'
				)
			);
			if ($events === null) {
				contextual_error_log('FtApiHandler: could not get upcoming events.');
				return (null);
			}
			$events = $this->pare_events_data($events);
			$this->cache['upcoming_events'] = $events;
			return ($events);
		}
		catch (Exception $ex) {
			contextual_error_log('FtApiHandler: could not get upcoming events. '
				.'Exception Message: '.$ex->getMessage());
			return (null);
		}
	}

	/**
	 * Gets event details
	 *
	 * Sets cache['events'][event_id]
	 */
	function	get_event($event_id) {
		try {
			$event = $this->get("/v2/events/$event_id", array());
			if ($event === null) {
				contextual_error_log("FtApiHandler: could not get event $event_id");
				return (null);
			}
			$event = $this->pare_event_data($event);
			$this->cache['events'][$event_id] = $event;
			return ($event);
		}
		catch (Exception $ex) {
			contextual_error_log("FtApiHandler: could not get event $event_id. "
				."Exception Message: ".$ex->getMessage());
			return (null);
		}
	}

	/**
	 * Gets details of current user
	 *
	 * Sets cache['me']
	 */
	function	get_me() {
		try {
			$me = $this->get("/v2/me", array());
			if ($me === null) {
				contextual_error_log("FtApiHandler: could not get user identity");
				return (null);
			}
			$me = $this->pare_user_data($me);
			$this->cache['me'] = $me;
			return ($me);
		}
		catch (Exception $ex) {
			contextual_error_log("FtApiHandler: could not user identity. "
				."Exception Message: ".$ex->getMessage());
			return (null);
		}
	}

	/**
	 * Gets the requested user
	 *
	 * Sets cache['users'][user_id]
	 */
	function	get_user($user_id) {
		try {
			$user = $this->get("/v2/users/$user_id", array());
			if ($user === null) {
				contextual_error_log("FtApiHandler: could not get user identity");
				return (null);
			}
			$user = $this->pare_user_data($user);
			$this->cache['users'][$user_id] = $user;
			return ($user);
		}
		catch (Exception $ex) {
			contextual_error_log("FtApiHandler: could not user identity. "
				."Exception Message: ".$ex->getMessage());
			return (null);
		}
	}

	/**
	 * Gets the requested event users
	 *
	 * Does not set email, first_name, or last_name
	 *
	 * Sets cache['event_users'][event_id]
	 */
	function	get_event_users($event_id) {
		try {
			$event_users = array();
			$page_size = 100;
			$page_number = 1;
			do {
				$data = $this->get("/v2/events/$event_id/events_users",
					array(
						'page[size]' => "$page_size",
						'page[number]' => "$page_number"
					)
				);
				if ($data) {
					$pared = $this->pare_event_users_data($data);
					$event_users = $event_users + $pared;
				}
				else {
					contextual_error_log("FtApiHandler: error getting page in event $event_id users data, skipped");
				}
				$page_number++;
			} while (count($data) == $page_size);
			$this->cache['event_users'][$event_id] = $event_users;
			return ($event_users);
		}
		catch (Exception $ex) {
			contextual_error_log("FtApiHandler: could not get event users for event $event_id. "
				."Exception Message: ".$ex->getMessage());
			return (null);
		}
	}

	/**
	 * Gets all events the given user is registered to
	 *
	 * Sets cache['user_events'][user_id]
	 */
	function	get_user_events($user_id) {
		try {
			$events = $this->get("/v2/users/$user_id/events", array());
			if ($events === null) {
				contextual_error_log("FtApiHandler: could not get events for user $user_id: get failed");
				return (null);
			}
			$events = $this->pare_events_data($events);
			$this->cache['user_events'][$user_id] = $events;
			return ($events);
		}
		catch (Exception $ex) {
			contextual_error_log("FtApiHandler: could not get events for user $user_id. "
				."Exception Message: ".$ex->getMessage());
			return (null);
		}
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

	//Pares an array of event arrays
	private function	pare_events_data(array $events) {
		$ret = array();
		foreach ($events as $key => $event) {
			$new_entry = $this->pare_event_data($event);
			$ret[$new_entry['event_id']] = $new_entry;
		}
		return ($ret);
	}
	
	//Should only be used with /v2/events/:event_id
	//or individual records from /v2/events
	private function	pare_event_data(array $event) {
		$ret = array();
		$ret['event_id'] = $event['id'];
		$ret['location'] = $event['location'];
		$ret['name'] = $event['name'];
		$ret['begin_at'] = $event['begin_at'];
		$ret['end_at'] = $event['end_at'];
		$ret['campus_id'] = $event['campus_ids'][0];
		return ($ret);
	}
	
	//Should only be used with /v2/events/.../events_users or /v2/users/.../events_users
	private function	pare_event_users_data(array $event_users) {
		$ret = array();
		foreach ($event_users as $key => $event_user) {
			$new_entry = $this->pare_event_user_data($event_user);
			$ret[$new_entry['user_id']] = $new_entry;
		}
		return ($ret);
	}
	
	//Should only be used with individual records within data from 
	///v2/events/.../events_users or /v2/users/.../events_users
	private function	pare_event_user_data(array $event_user) {
		$ret = array();
		$ret['email'] = '';
		$ret['user_id'] = $event_user['user_id'];
		$ret['login'] = $event_user['user']['login'];
		$ret['first_name'] = '';
		$ret['last_name'] = '';
		$ret['campus_id'] = $event_user['event']['campus_ids'][0];
		return ($ret);
	}
	
	//Should only be used with /v2/users/:user_id
	//or individual records from /v2/users
	private function	pare_user_data(array $user) {
		$ret = array();
		$ret['email'] = $user['email'];
		$ret['user_id'] = $user['id'];
		$ret['login'] = $user['login'];
		$ret['first_name'] = $user['first_name'];
		$ret['last_name'] = $user['last_name'];
		$ret['campus_id'] = $user['campus'][0]['id'];
		return ($ret);
	}

}

?>
