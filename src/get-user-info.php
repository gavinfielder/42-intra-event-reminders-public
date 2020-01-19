<?php
require_once('src/auth.php');
require_once('src/api.php');

function get_user_info($token) {
	return (ft_api_get('/v2/me', array(), $token));
}
