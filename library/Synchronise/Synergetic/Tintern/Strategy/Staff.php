<?php
/**
 * Synchronise_Synergetic_Tintern_Strategy_Staff
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
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
        switch($currentUser['StaffDepartment']) {
            case 'JSO':
                return 4;
                break;

            case 'JSSPEC':
                return 4;
                break;

            case 'JSTEACH':
                return 4;
                break;

            default:
                return 1;
              break;
        }
    }

    public function getCampusListFromUser($currentUser) {
        switch($currentUser['StaffDepartment']) {
            case 'JSO':
                return array(4,5);
            break;

            case 'JSSPEC':
                return array(4,5);
            break;

            case 'JSTEACH':
                return array(4,5);
            break;

            default:
                return array(1,2,3);
            break;
        }
    }

    /**
	 * All staff are thrown into group id 3 (Secondary Staff) for now
	 *
     * @param  array $currentUser
     * @return int
     */
    public function getGroupIDFromUser($currentUser) {
        switch($currentUser['StaffDepartment']) {
            case 'JSO':
                return 2;
                break;

            case 'JSSPEC':
                return 2;
                break;

            case 'JSTEACH':
                return 2;
                break;

            default:
                return 3;
                break;
        }
    }

	/**
	 * Webmail setting
	 *
	 * @param $currentUser
	 * @return int|void
	 */
	public function getWebmailFromUser($currentUser) {
		return 1;
	}

	/**
	 * External mail setting
	 *
	 * @param $currentUser
	 * @return int|void
	 */
	public function getExternalMailFromUser($currentUser) {
		return 0;
	}

	/**
	 * Eportfolio Enabled setting
	 *
	 * @param array $currentUser
	 * @return bool|int
	 */
	public function getEportfolioEnabledFromUser($currentUser) {
		return 1;
	}
}
