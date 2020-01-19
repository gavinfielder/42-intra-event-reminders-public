<?php

require_once(__DIR__.'/../settings.php');


/**
 * This function is used to make sure we don't send messages to
 * the general public while the app is in development
 *
 * Returns true if whitelisted, false if not
 */
function	dev_filter_user_is_whitelisted($user) {
	if (!isset($user['login']))
		return (false);
	if ($user['login'] == 'gfielder')
		return (true);
	if ($user['login'] == 'dcelojev')
		return (true);
	if (USE_EXTENDED_WHITELIST && file_exists(__DIR__.'/../databases/extended_whitelist.txt')) {
		if (strpos(file_get_contents(__DIR__.'/../databases/extended_whitelist.txt'), $user['login']) !== false)
			return (true);
	}
	return (false);
}

function	filter_by_campus($campus_id) {
	if ($campus_id == 7)
		return (true);
	return (false);
}

?>
