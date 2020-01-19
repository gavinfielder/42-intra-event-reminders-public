<?php

require_once(__DIR__.'/logging.php');

function	contextual_error_log($msg) {
	if (php_sapi_name() == 'cli')
		cli_error_log($msg);
	else
		http_error_log($msg);
}

function	cli_error_log($msg) {
	cli_log("[ERROR] $msg");
}

function	cli_error($msg, bool $fatal = false) {
	cli_error_log($msg);
	if ($fatal) {
		trigger_error($msg, E_ERROR);
	}
}

function	http_error_log($msg) {
	error_log($msg);
}

?>
