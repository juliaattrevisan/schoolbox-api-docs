<?php

class External_Schoolpro_Ping implements External_PingInterface {

	/**
	 * Connects to Schoolpro database in the same manner that the other classes
	 * would, and returns whether the database is responsive.
	 *
	 * @return bool
	 */
    public function isResponsive() {
		$db = new MSSQL();

		return $db->isConnected();
	}
}