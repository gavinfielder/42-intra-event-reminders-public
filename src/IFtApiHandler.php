<?php

/**
 * Interface for 42 API data
 *
 * All data is returned as an associative array.
 *
 * For events, these keys will be guaranteed to be set:
 *    'event_id'
 *    'location'
 *    'name'
 *    'begin_at'
 *    'end_at'
 *    'campus_id'
 *
 * For users, these keys will be guaranteed to be set:
 *    'user_id'
 *    'login'
 *    'campus_id'
 *    'first_name'     *
 *    'last_name'      *
 *    'email'          *
 *
 * get_user(user_id), get_me(), and access_me() are all
 * guaranteed to fill all of the above fields. For all
 * other calls that return user data, the fields marked
 * with * are not guaranteed to be filled. If not filled,
 * the key will be set but value will be empty string.
 */
interface IFtApiHandler {
	//Generally needed for initialization
	public function set_token($token);

	//Access local cached data if available, call api if not
	public function access_event($event_id);
	public function access_upcoming_events();
	public function access_me();
	public function access_user($user_id);
	public function access_event_users($event_id);
	public function access_user_events($user_id);

	//Call api regardless of local cached data
	public function get($endpoint, array $params);
	public function get_upcoming_events();
	public function get_event($event_id);
	public function get_me();
	public function get_user($user_id);
	public function get_event_users($event_id);
	public function get_user_events($user_id);

	//If the implementation uses local cached data, this clears it
	public function clear_cached_data($only_key = null);

	//Accesses the current token
	public function get_current_token();
}

?>
