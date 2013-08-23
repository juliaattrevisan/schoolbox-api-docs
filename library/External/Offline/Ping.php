<?php

class External_Offline_Ping implements External_PingInterface {

	/**
	 * Connects to Synergetic database, and returns whether the database is
	 * responsive.
	 *
	 * @return bool
	 */
    public function isResponsive() {
	    return false;
    }
}