<?php
/**
 * St Michaels-specific methods that are provided for each synchronisation Strategy.
 *
 * You can create custom subclasses of Synchronise_Synergetic_Strategy_Abstract to
 * provide unique methods to your synchronisation strategies.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Synchronise_Synergetic_Jpc_Strategy_Abstract extends Synchronise_Synergetic_Strategy_Abstract {
	protected $verboseFilter = null;

	/**
	 * Identical algorithm used to create/update a user
	 *
	 * @param  $newUser
	 * @return void
	 */
	protected function executeCreateUpdate($newUser) {

		/* Prepare verbosity
		 */
		$this->getVerboseFilter()->setNewUser($newUser);

		/* Does this person have a username? If not, skip them
		 */
		if ($this->hasUsername($newUser)) {
			$existing = $this->userExists($newUser);

			/* If the current user exists in Schoolbox, update them
			 */
			if ($existing) {

				/* Perform action; report what happened
				 */
                //Leave groups alone once user is defined (unless a student)
				$newUser = $this->alterBeforeUpdate($newUser);

				if ($this->getPerformUpdate() === true) {
					$newUser['id'] = $existing['uid'];
					$this->getUserHandler()->update($newUser);
					$this->getVerboseFilter()->performUpdate();
					$this->getVerboseFilter()->incrementUpdated();
				}
				/* Report intentions
				 */
				else {
					$this->getVerboseFilter()->planUpdate();
					$this->getVerboseFilter()->incrementUpdated();
				}
			}
			/* Otherwise create them
			 */
			else {
				/* Perform action; report what happened
				 */



				if ($this->getPerformUpdate() === true) {
					$this->getUserHandler()->create($newUser);
					$this->getVerboseFilter()->performCreation();
					$this->getVerboseFilter()->incrementCreated();
				}
				/* Report intentions
				 */
				else {
					$this->getVerboseFilter()->planCreation();
					$this->getVerboseFilter()->incrementCreated();
				}
			}
		}
		/* Report intentions
		 */
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

		/* Only one campus at JPC
		 */
		return 1;
	}

	/**
	 * Figure out the Group ID based on a Student's year level
	 *
	 * @param  $year
	 * @return bool|int
	 */
	protected function getGroupIDFromYearLevel($year) {
		switch ($year) {
			case '7':
				return 5;
			case '8':
				return 6;
			case '9':
				return 7;
			case '10':
				return 8;
			case '11':
				return 9;
			case '12':
				return 10;
			default:
				return false;
		}
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
	 * Determine if user exists in Schoolbox
	 *
	 * @param $newUser
	 * @return mixed
	 */
	protected function userExists($newUser) {
		return $this->getUserHandler()->getBySynergy($newUser['synergy_id']);
	}

	/**
	 * Mangles $newUser before they are updated. All users remain in the same
	 * group, except for students (who move between year levels).
	 *
	 * @param $newUser
	 *
	 * @return mixed
	 */
	protected function alterBeforeUpdate($newUser) {
		unset($newUser['group_id']);
		return $newUser;
	}
}