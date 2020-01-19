<?php

require_once(__DIR__.'/../settings.php');

$log_buffer = "";

function	cli_log($msg) {
	global $log_buffer;
	$msg = date('Y-m-d H:i:s').": $msg\n";
	if (!BUFFER_LOGGING)
		echo $msg;
	else
		$log_buffer .= $msg;
}

function	contextual_log($msg) {
	if (php_sapi_name() == 'cli')
		cli_log($msg);
	else
		http_log($msg);
}

function	http_log($msg) {
	error_log($msg);
}

function	flush_logs() {
	global $log_buffer;
	echo $log_buffer;
	$fout = fopen(LOG_FILENAME, "a");
	fwrite($fout, $log_buffer);
	fclose($fout);
	$log_buffer = "";
}

?>
