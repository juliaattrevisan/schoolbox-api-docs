<?php
/**
 * Synchronise_Synergetic_Tintern_Strategy_Staff
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_Tintern_Strategy_Staff extends Synchronise_Base_Strategy_Staff {
    protected $title = "Update Schoolbox Staff Details Synchronisation";

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Tintern_Verbose();
    }

    /**
     * Gets campus according to the following map:
	 *
	 * All staff are in the Senior College campus
     *
     * @param  array $currentUser
     * @return int
     */
    public function getDefaultCampusFromUser($currentUser) {
		return 1;
    }

    /**
	 * All staff are thrown into group id 3 (Secondary Staff) for now
	 *
     * @param  array $currentUser
     * @return int
     */
    public function getGroupIDFromUser($currentUser) {
		return 3;
    }
}
