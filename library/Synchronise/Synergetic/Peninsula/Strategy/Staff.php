<?php
/**
 * Synchronise_Synergetic_Peninsula_Strategy_Staff
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_Peninsula_Strategy_Staff extends Synchronise_Base_Strategy_Staff {
    protected $title = "Update Schoolbox Staff Details Synchronisation";

    protected $campusList = array();

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Peninsula_Verbose();
        foreach (\Schoolbox\CampusMapper::getInstance()->findAll() as $campus) {
            $this->campusList[] = $campus->getId();
        }
    }

    /**
     * Gets campus according to the following map:
     *
     * * E      => Early Childhood (4)
     * * J      => Junior (3)
     * * M      => Middle (2)
     * * S, ELC => Senior (1)
     *
     * Defaults to senior.
     *
     * @param  array $currentUser
     * @return int
     */
    public function getDefaultCampusFromUser($currentUser) {
        $campus_code = strtoupper($currentUser['StaffCampus']);
        switch ($campus_code) {
            case 'S':
            case 'ELC':
                return 1;
            case 'M':
                return 2;
            case 'J':
                return 3;
            case 'E':
                return 4;
            default:
                break;
        }
        return 1;
    }

    /**
     * Just add staff members to all campuses.
     *
     * @param  array $currentUser
     * @return array
     */
    public function getCampusListFromUser($currentUser) {
        return $this->campusList;
    }

    /**
     * @param  array $currentUser
     * @return int
     */
    public function getGroupIDFromUser($currentUser) {
        switch ($currentUser['StaffCategory']) {
            // Academic Staff
            case 'AADPR':
            case 'ADPOP':
            case 'ADVPR':
            case 'CHAPLAIN':
            case 'LIBRARY':
            case 'MUSICCAS':
            case 'MUSICSAL':
            case 'TEACHKIN':
            case 'TEACHPRI':
            case 'TEACHSEC':
                return 2;

            // Non-Academic Staff
            case 'ADWBMGR':
            case 'ASS':
            case 'BOARDHSE':
            case 'BUSTEAMACC':
            case 'BUSTEAMADMIN':
            case 'BUSTEAMARCH':
            case 'BUSTEAMIT':
            case 'CRADM':
            case 'CRMARK':
            case 'CSLR':
            case 'GAPSTUD':
            case 'MISC':
            case 'OTHER':
            case 'PROPERTY':
            case 'SCHLT':
            case 'STUDSERV':
            case 'TEACHAIDE':
            case 'TUCKSHOP':
            case 'CLEAN':
            case 'CASRELTEA':
            default:
                return 3;
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
            return 1;
    }
}
