<?php
require_once(__DIR__ . '/../settings.php');
require_once(__DIR__."/logging.php");
require_once(__DIR__."/error.php");

function	use_template($template_name, $fields) {
	$template = file_get_contents(__DIR__."/../assets/templates/$template_name");
	if (!$template) {
		cli_error_log("Could not retreive template '$template'");
		return (false);
	}
	$patterns = array();
	$replacements = array();
	$patterns[] = '/\{APP_URL\}/';
	$replacements[] = FT_API_REDIRECT_URI;
	if (isset($fields['event'])) {
		$patterns[] = '/\{EVENT_NAME\}/';
		$replacements[] = $fields['event']['name'];
		$patterns[] = '/\{EVENT_LOCATION\}/';
		$replacements[] = $fields['event']['location'];
	}
	if (isset($fields['email_address'])) {
		$patterns[] = '/\{USER_EMAIL\}/';
		$replacements[] = $fields['email_address'];
	}
	if (isset($fields['confirmation_code'])) {
		$patterns[] = '/\{CONFIRMATION_CODE\}/';
		$replacements[] = $fields['confirmation_code'];
	}
	if (isset($fields['time'])) {
		$patterns[] = '/\{EVENT_TIME\}/';
		$replacements[] = $fields['time']->format('g:ia');
		$patterns[] = '/\{EVENT_DATE\}/';
		$replacements[] = $fields['time']->format('l F jS');
	}
	if (isset($fields['user'])) {
		$patterns[] = '/\{USER_LOGIN\}/';
		$replacements[] = $fields['user']['login'];
	}
	$msg = preg_replace($patterns, $replacements, $template);
	return ($msg);
}

?>
