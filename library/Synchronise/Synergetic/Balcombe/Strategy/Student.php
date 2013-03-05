<?php
/**
 * Synchronise_Synergetic_Balcombe_Strategy_Student
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * Adds Students in Synergetic to the Schoolbox User table.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Balcombe_Strategy_Student extends Synchronise_Synergetic_Balcombe_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Student Details Synchronisation";

	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_Balcombe_Verbose_Student());
	}

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$this->collateList($this->getExternalUserHandler()->getStudentUserAccountInfo());
	}

	/**
	 * Set up the new user's details
	 *
	 * @param  $currentUser
	 * @return array
	 */
	public function setupUserDetails($currentUser) {
		$newUser = array();

        // User details
        $newUser['username'] = $currentUser['NetworkLogin'];
		$newUser['title'] = '';
		$newUser['firstname'] = $currentUser['StudentPreferred'];
		$newUser['lastname'] = $currentUser['StudentSurname'];
        $newUser['alt_email'] = '';
        $newUser['synergy_id'] = $currentUser['StudentID'];
        $newUser['local_email'] = 1;
        $newUser['eportfolio_enabled'] = 1;

		// Year level
		$newUser['year'] = $currentUser['StudentYearLevel'];

        // we are only including student in the 'B' campus in this sync
        if($currentUser['StudentCampus'] !== 'B') {
            return false;
        }

		// Campus config: needs to map to a campus id
		$newUser['defaultCampus'] = 1;
		$newUser['campus'] = array(1);

		// Student Group ID
		$newUser['group_id'] = $this->getGroupIDFromYearLevel($currentUser['StudentYearLevel']);

		return $newUser;
	}

	/**
	 * If the users exist in Synergy but not in Schoolbox, create an account for
	 * them
	 *
	 * @return void
	 */
	public function createIfExists() {
		$this->getVerboseFilter()->intro();

		foreach($this->getList() as $currentUser) {

            // if the student was skipped in setup, don't attempt to create/update them
            if($currentUser === false) {
                continue;
            }

			/* Generate details about the new user
			 */
			$this->executeCreateUpdate($this->setupUserDetails($currentUser));
		}

		$this->getVerboseFilter()->summary();
	}

    /**
     * Determine if user exists in Schoolbox
     *
     * @param $newUser
     * @return array|bool
     */
    protected function userExists($newUser) {
        return $this->getUserHandler()->getBySynergy($newUser['synergy_id']);
    }
}