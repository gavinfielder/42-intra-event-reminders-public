<?php

function open_database($db, $path) {
	$db->open($path);
	if (php_sapi_name() == 'cli') {
		chmod($path, 0664);
		chgrp($path, 'www');
	}
}
