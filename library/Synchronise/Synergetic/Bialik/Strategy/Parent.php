<?php
/**
 * Adds Parents in Synergetic to the Schoolbox User table. Applies Campus based
 * on the Students' Year Level.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Bialik_Strategy_Parent extends Synchronise_Synergetic_Bialik_Strategy_Abstract {
	protected $strategyTitle = "Parent Synchronisation";
	const PARENT_GROUP_ID = 4;

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$this->collateList($this->getExternalUserHandler()->getParentUserAccountInfo());
	}

	/**
	 * Set up the new user's details
	 *
	 * @param  $currentUser
	 * @return array
	 */
	public function setupUserDetails($currentUser) {
		$newUser = array();

		/* Login details
		 * We don't know how the password is given to us
		 */
		$newUser['username'] = $currentUser['StudentContactID'];
		$newUser['password'] = '1234';

		/* User details
		 */
		$newUser['title'] = $currentUser['StudentContactTitle'];
		$newUser['firstname'] = $currentUser['StudentContactPreferred'];
		$newUser['lastname'] = $currentUser['StudentContactSurname'];
		$newUser['alt_email'] = $currentUser['StudentContactDefaultEmail'];

		/* Campus config: needs to map to a campus id
		 */
		$newUser['defaultCampus'] = 1;
		$newUser['campus'] = array($this->getCampusFromYearLevel($currentUser['StudentYearLevel']));

		/* Staff Group ID
		 */
		$newUser['group_id'] = self::PARENT_GROUP_ID;

		/* This person is not a superuser
		 */
		$newUser['su'] = false;

		return $newUser;
	}

	/**
	 * If the users exist in Synergy but not in Schoolbox, create an account for
	 * them
	 *
	 * @return void
	 */
	public function createIfExists() {
		foreach($this->getList() as $currentUser) {
			$this->executeCreateUpdate($currentUser);
		}
	}
}