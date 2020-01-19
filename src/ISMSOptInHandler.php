<?php

interface ISMSOptInHandler {
	public function get_optin_info($number); //returns full array
	public function get_optin_status($number); //returns status string only
	//Possible statuses: 'new', 'awaiting_confirmation', 'confirmed'
	public function generate_confirmation_code($number);
	public function confirm($number, $confirmation_code);
}

?>
