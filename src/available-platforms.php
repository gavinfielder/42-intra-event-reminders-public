<?php

function check_platform_status($platform, $campus_id) {
	$campus = array(
		//Abbreviation => Campus ID
		'SV' => 7
	);

	switch ($platform) {
		case 'whatsapp':
			return (false);

		case 'sms':
			return ($campus_id == $campus['SV']);

		case 'slack':
			return ($campus_id == $campus['SV']);

		default:
			return (true);
	}
}
