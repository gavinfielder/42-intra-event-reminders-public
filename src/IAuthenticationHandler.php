<?php

interface	IAuthenticationHandler {
	public function authorize();
	public function check_token_validity($token);
	public function refresh_access();
	public function get_current_token();
}

?>
