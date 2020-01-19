<?php

require_once(__DIR__.'/IFtApiHandler.php');

interface ICampusesDataHandler {
	public function access_campuses();
	public function update_campuses(IFtApiHandler $api);
}

?>
