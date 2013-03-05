<?php
/**
 * Balcombe-specific methods that are provided for each synchronisation Strategy.
 *
 * You can create custom subclasses of Synchronise_Synergetic_Strategy_Abstract to
 * provide unique methods to your synchronisation strategies.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Synchronise_Synergetic_Balcombe_Strategy_Abstract extends Synchronise_Synergetic_Strategy_Abstract {

    const PARENT_GROUP_ID = 20;
    const TEACH_GROUP_ID = 4;
    const DEFAULT_CAMPUS = 1;

	protected $verboseFilter = null;

	/**
	 * Identical algorithm used to create/update a user
	 *
	 * @param  $newUser
	 * @return void
	 */
	protected function executeCreateUpdate($newUser) {

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
					$this->getVerboseFilter()->performUpdate();
					$this->getVerboseFilter()->incrementUpdated();
				}
				// Report intentions
				else {
					$this->getVerboseFilter()->planUpdate();
					$this->getVerboseFilter()->incrementUpdated();
				}
			}
			// Otherwise create them
			else {
				// Perform action; report what happened
				if ($this->getPerformUpdate() === true) {
					$this->getUserHandler()->create($newUser);
					$this->getVerboseFilter()->performCreation();
					$this->getVerboseFilter()->incrementCreated();
				}
				// Report intentions
				else {
					$this->getVerboseFilter()->planCreation();
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

	/**
	 * Figure out which campus a student member is in, based on the code provided
	 *
	 * @param  $code
	 * @return bool|string
	 */
	protected function getCampusFromCode($code) {
        // at time of writing, there are 3 campuses listed inside Schoolbox, however everyone is being assigned to BGS(1)
        return 1;
	}

	/**
	 * Figure out the Group ID based on a Student's year level
	 *
	 * @param  $year
	 * @return bool|int
	 */
	protected function getGroupIDFromYearLevel($year) {
        // group IDs are conveniently sequential in this install
		if($year >= 0 && $year <= 12) {
            return $year + 7;
        }
        return false;
	}

	/**
	 * Determine if this person has a username
	 *
	 * @param  $currentUser
	 * @return bool
	 */
	protected function hasUsername($currentUser) {
		if (strlen(trim($currentUser['username']))) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if user exists in Schoolbox, to be implemented by extending classes
	 *
	 * @param $newUser
	 * @return mixed
	 */
	abstract protected function userExists($newUser);
}