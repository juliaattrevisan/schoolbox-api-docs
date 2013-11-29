<?php
/**
 * Synchronise_Synergetic_Iggs_Strategy_Staff
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_Iggs_Strategy_Staff extends Synchronise_Base_Strategy_Staff {
    protected $title = "Update Schoolbox Staff Details Synchronisation";

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Iggs_Verbose();
    }

    /**
     * Allows user to be created if the staff member has a campus and a username
     *
     * @param  $currentUser
     * @return bool
     */
    protected function hasUsername($currentUser) {
        if (parent::hasUsername($currentUser)) {
            $campusList = array_filter($currentUser['campus']);
            return !empty($campusList);
        }
        return false;
    }

    /**
     * Staff member's username is based on their username if it's there.
     * Otherwise, it's generated from the first letter of their first name, then
     * their surname (all lower cased)
     *
     * @param  array  $currentUser
     * @return string
     */
    public function getUsernameFromUser($currentUser) {
        $username = parent::getUsernameFromUser($currentUser);
        if (!(strlen($username) > 0)) {
            $first_initial = mb_substr($this->getFirstNameFromUser($currentUser), 0, 1);
            $surname       = $this->getLastNameFromUser($currentUser);
            $username      = strtolower($surname . $first_initial);
        }
        return $username;
    }

    /**
     * Gets campus according to the following map:
     *
     * * S (Senior) => 1
     * * J (Junior) => 2
     * * E (ELC)    => 3
     *
     * @param  array $currentUser
     * @return int
     */
    public function getDefaultCampusFromUser($currentUser) {
        switch (strtoupper($currentUser['StaffCampus'])) {
            case 'S':
                return 1;
            case 'J':
                return 2;
            case 'E':
                return 3;
        }
        return 0;
    }

    /**
     * Gets Schoolbox group from the external data set.
     * * TEACH  => Teaching (group 2)
     * * MAINT  => non teaching (group 21)
     * * ADMIN  => non teaching
     * * MUTEA  => music (group 22)
     * * ASSIST => non teaching
     * * CASUAL => non teaching
     * * CRT    => teaching
     *
     * @param  array $currentUser
     *
     * @return int
     */
    public function getGroupIDFromUser($currentUser) {
        switch (strtoupper($currentUser['StaffCategory'])) {
            case 'TEACH':
            case 'CRT':
                return 2;
            case 'MAINT':
            case 'ADMIN':
            case 'ASSIST':
            case 'CASUAL':
                return 21;
            case 'MUTEA':
                return 22;
        }
        return 0;
    }
}
