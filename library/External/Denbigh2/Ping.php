<?php

class External_Denbigh2_Ping implements External_PingInterface {

	/**
	 * Connects to Denbigh2 API in the same manner that the other classes
	 * would, and returns whether the database is responsive.
	 *
	 * @return bool
	 */
    public function isResponsive() {

        /* Use a cheep api call to test
         */
        $api = new External_Denbigh2_Api('PeriodTimes', array());

        if (!$api->hasErrors()) {
            return true;
        }

        return false;
	}
}
