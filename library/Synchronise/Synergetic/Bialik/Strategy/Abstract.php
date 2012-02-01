<?php
/**
 * Bialik-specific methods that are provided for each synchronisation Strategy.
 *
 * You can create custom subclasses of SynergeticSyncStrategy to provide unique
 * methods to your synchronisation strategies.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Synchronise_Synergetic_Bialik_Strategy_Abstract extends Synchronise_Synergetic_Strategy_Abstract implements Synchronise_Synergetic_Bialik_Strategy_Interface {
	protected $verboseFilter = null;

	/**
	 * Construct
	 *
	 * Instantiate additional classes
	 */
	public function __construct() {
		parent::__construct();

		$this->setVerboseFilter(new Synchronise_Synergetic_Bialik_Verbose());
	}

	/**
	 * Figure out the Group ID based on a Student's year level
	 *
	 * @param  $year
	 * @return bool|int
	 */
	protected function getGroupIDFromYearLevel($year) {
		switch ($year) {
			case '01':
			case '02':
			case '03':
			case '04':
			case '05':
				return 8;
				break;
			case '06':
			case '07':
			case '08':
			case '09':
				return 7;
				break;
			case '10':
			case '11':
			case '12':
				return 6;
				break;
			case '00':
			case '30':
			case '40':
				return 9;
				break;
			default:
				return false;
				break;
		}
	}

	/**
	 * Figure out which Campus belongs to this Student
	 *
	 * @param  $year
	 * @return bool|string
	 */
	protected function getCampusFromYearLevel($year) {
		switch ($year) {
			case '02':
			case '03':
			case '04':
			case '05':
			case '06':
			case '07':
			case '08':
			case '09':
			case '10':
			case '11':
			case '12':
				//return 'main';
				return 1;
				break;
			case '00':
			case '01':
			case '30':
			case '40':
				//return 'elc';
				return 2;
				break;
			default:
				return false;
				break;
		}
	}

	/**
	 * Figure out which campus a staff member is in, based on the code provided
	 *
	 * @param  $code
	 * @return bool|string
	 */
	protected function getCampusFromCode($code) {
		switch($code) {
			case 'S':
			case 'M':
			case 'P':
				// return 'main';
				return 1;
				break;
			case 'ELC':
				// return 'elc';
				return 2;
				break;
			default:
				return false;
				break;
		}
	}

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
	 * Determine if user exists in Schoolbox, by seeing if we can
	 * get someone by their username
	 *
	 * @param $newUser
	 * @internal param $currentUser
	 * @return void
	 */
	protected function userExists($newUser) {
		return $this->getUserHandler()->getBySynergy($newUser['synergy_id']);
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
}