<?php

class External_Synergetic_Ping implements External_PingInterface {

	/**
	 * Connects to Synergetic database, and returns whether the database is
	 * responsive.
	 *
	 * @return bool
	 */
    public function isResponsive() {
	    if (function_exists('mssql_connect')) {
		    return @mssql_connect(
			    Cfg::get('external_db_host'),
			    Cfg::get('external_db_user'),
			    Cfg::get('external_db_passwd')
		    ) !== false;
	    }
	    return false;
    }
}