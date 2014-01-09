<?php
/**
 * Synchronise_Synergetic_Iggs_Strategy_Parent
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_Iggs_Strategy_Parent extends Synchronise_Base_Strategy_Parent {
    protected $title = "Update Schoolbox Parent Details Synchronisation";

    /**
     * Instance of Synchronise_Synergetic_Iggs_Strategy_Student used as a helper
     * for retrieving campus from user input.
     *
     * @var Synchronise_Synergetic_Iggs_Strategy_Student
     */
    protected $studentStrategy;

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Iggs_Verbose();
        $this->studentStrategy = new Synchronise_Synergetic_Iggs_Strategy_Student();
    }

    /**
     * Overridden to retrieve campus list from student sync
     *
     * @param  array $currentUser
     * @return array
     */
    public function getCampusListFromUser($currentUser) {
        return $this->studentStrategy->getCampusListFromUser($currentUser);
    }

    /**
     * Overridden to retrieve campus list from student sync
     *
     * @param  array $currentUser
     * @return array
     */
    public function getSpouseCampusListFromUser($currentUser) {
        return $this->studentStrategy->getCampusListFromUser($currentUser);
    }
}
