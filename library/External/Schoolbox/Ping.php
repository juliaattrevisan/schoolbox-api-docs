<?php

class External_Schoolbox_Ping implements External_PingInterface {

	/**
	 * Connects to Schoolbox database in the same manner that the other classes
	 * would, and returns whether the database is responsive.
	 *
	 * @return bool
	 */
    public function isResponsive() {
		return Database::getInstance()->isConnected();
    }
}