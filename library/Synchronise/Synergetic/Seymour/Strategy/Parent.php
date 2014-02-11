<?php
/**
 * Synchronise_Synergetic_Seymour_Strategy_Parent
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Seymour_Strategy_Parent extends Synchronise_Synergetic_Seymour_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Parent Details Synchronisation";

	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_Seymour_Verbose_Parent());
	}

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
        $this->collateList($this->getExternalUserHandler()->getParentUserAccountInfo());
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

			/* Generate details about the new user
			 */
			$this->executeCreateUpdate($this->setupUserDetails($currentUser));
		}

		$this->getVerboseFilter()->summary();
	}

	/**
	 * Set up the new user's details
	 *
	 * @param  $currentUser
	 * @return array
	 */
	public function setupUserDetails($currentUser) {
		return $currentUser;
	}

	/**
	 * Identical algorithm used to create/update a user
	 *
	 * @param $currentUser
	 */
	public function executeCreateUpdate($currentUser) {
//        print_r($currentUser);
		$currentSynergyId = $currentUser['StudentContactID'];
		$currentParent = $this->getExternalUserHandler()->getParentSync($currentSynergyId);

//        print_r($currentParent);

		// If the parent could be found, try updating him in Synergetic
		if ($currentParent['ID']) {

			// The drop down in synergetic's Phone and Occupation tabs indicates which email the person prefers to use.
			if ($currentParent['DefaultEmailCode'] == 'O') {
				$preferredEmail = $currentParent['OccupEmail'];
			}
			else {
				$preferredEmail = $currentParent['Email'];
			}

			// Update Portal with Synergetic data if there is something different contained in Synergetic db
			$user = $this->getUserHandler()->getBySynergyAndGroup($currentSynergyId, 4);

            if($user) {
                // data mismatch, update
                // use the original data rather than the reloaded for the password
                $newUser['id'] = $user['uid'];
                $newUser['username'] = $user['username'];

            }
            // does not exist in schoolbox: create
            else {
                $newUser['username'] = trim($currentParent['ID']);
            }

            /* Set all user data */
            $newUser['title'] = trim($currentParent['Title']);
            $newUser['firstname'] = trim($currentParent['Preferred']);
            $newUser['lastname'] = trim($currentParent['Surname']);

            $newUser['campus'] = array();
            $students = $this->getExternalUserHandler()->getEnrolledStudents($currentSynergyId);
            if(is_array($students)) {
                foreach($students as $child) {
                    $childUser = $this->getUserHandler()->getBySynergy($child['RelatedID']);
                    $campuses = $this->getUserHandler()->getUserCampus($childUser['uid']);
                    $newUser['campus'] = array_merge($newUser['campus'], $campuses);
                }
                $newUser['campus'] = array_unique($newUser['campus']);
            }
            $newUser['defaultCampus'] = $newUser['campus'][0];

            $newUser['alt_email'] = trim($preferredEmail);
            $newUser['synergy_id'] = $currentSynergyId;

            // Prepare verbosity
            $this->getVerboseFilter()->setNewUser($newUser);

            // Does this person have a username? If not, skip them
            if ($this->hasUsername($newUser)) {
                $existing = $this->userExists($newUser);

                // If the current user exists in Schoolbox, update them
                if ($existing) {

                    // Perform action; report what happened
                    if ($this->getPerformUpdate() === true) {
                        $newUser['id'] = $existing['uid'];

                        $this->getUserHandler()->update($newUser);
                        $this->getVerboseFilter()->performUpdate($user, $currentSynergyId, $newUser, $currentParent);
                        $this->getVerboseFilter()->incrementUpdated();
                    }
                    // Report intentions
                    else {
                        $this->getVerboseFilter()->planUpdate($user, $currentSynergyId, $newUser, $currentParent);
                        $this->getVerboseFilter()->incrementUpdated();
                    }
                }
                // Otherwise create them
                else {
                    // Perform action; report what happened
                    if ($this->getPerformUpdate() === true) {
                        $this->getUserHandler()->create($newUser);
                        $this->getVerboseFilter()->performCreation($user, $currentSynergyId, $newUser, $currentParent);
                        $this->getVerboseFilter()->incrementCreated();
                    }
                    // Report intentions
                    else {
                        $this->getVerboseFilter()->planCreation($user, $currentSynergyId, $newUser, $currentParent);
                        $this->getVerboseFilter()->incrementCreated();
                    }
                }
            }
            // Report intentions
            else {
                $this->getVerboseFilter()->skip();
                $this->getVerboseFilter()->incrementSkipped();
            }

        }
	}
}