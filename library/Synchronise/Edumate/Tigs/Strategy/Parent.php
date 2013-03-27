<?php
/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Synchronise_Edumate_Tigs_Strategy_Parent extends Synchronise_Edumate_Tigs_Strategy_Abstract {

	/**
	 * Get a list of records to process.
	 *
	 * Copy-pasted, should be in base class.
	 *
	 * @return void
	 */
	public function generateList() {
//		echo '$b=';var_export($this->getExternalUserHandler()->getParentUserAccountInfo());echo ";\n";
		$this->collateList($this->getExternalUserHandler()->getParentUserAccountInfo());
	}

	/**
	 * Set up the new user's details
	 *
	 * This should be the only method that needs to be overridden.
	 *
	 * @param  $currentUser
	 * @return array
	 */
	public function setupUserDetails($currentUser) {
		// User details
		$newUser['username']      = $currentUser['StudentContactID'];
		$newUser['synergy_id']    = $currentUser['StudentContactID'];
		$newUser['title']         = $currentUser['StudentContactTitle'];
		$parts = preg_split('/\s+/', $currentUser['StudentContactPreferred'], 2);

		list($newUser['firstname'], $newUser['lastname']) = preg_split('/\s+/', $currentUser['StudentContactPreferred'], 2);
		$newUser['alt_email']     = $currentUser['StudentContactDefaultEmail'];
		$newUser['defaultCampus'] = $this->getCampusFromYearLevel($currentUser['StudentYearLevel']);

		// get campuses of attached children
		$newUser['campus']        = array($this->getCampusFromYearLevel($currentUser['StudentYearLevel']));
		$newUser['group_id']      = 0;

		return $newUser;
	}

	/**
	 * Set up the spouse's details
	 *
	 * @param  $currentUser
	 * @return array
	 */
	public function setupSpouseDetails($currentUser) {
		if (empty($currentUser['StudentContactSpouseID'])) {
			return array();
		}
		$newUser['username']      = $currentUser['StudentContactSpouseID'];
		$newUser['synergy_id']    = $currentUser['StudentContactSpouseID'];
		$newUser['title']         = $currentUser['StudentContactSpouseTitle'];
		list($newUser['firstname'], $newUser['lastname']) = preg_split('/\s+/', $currentUser['StudentContactSpousePreferred'], 2);
		$newUser['alt_email']     = $currentUser['StudentContactSpouseDefaultEmail'];
		$newUser['defaultCampus'] = $this->getCampusFromYearLevel($currentUser['StudentYearLevel']);

		// get campuses of attached children
		$newUser['campus']        = array($this->getCampusFromYearLevel($currentUser['StudentYearLevel']));
		$newUser['group_id']      = 0;

		return $newUser;
	}

	/**
	 * Process the users
	 *
	 * Users includes both base users and spouses, this attempts to get them as
	 * unique users and add them in one hit.
	 */
	public function createIfExists() {
		$this->getVerboseFilter()->intro();

		$users = array();

		//
		// First pass: get unique users
		foreach ($this->getList() as $user) {
			$users = $this->mergeUserDetails($this->setupUserDetails($user),   $users);
			$users = $this->mergeUserDetails($this->setupSpouseDetails($user), $users);
		}

		//
		// Second pass: insert into DB
		foreach ($users as $user) {
			$this->executeCreateUpdate($user);
		}

		$this->getVerboseFilter()->summary();
	}


	protected function mergeUserDetails($new_user, $existing_users) {
		if (empty($new_user['username'])) {
			return $existing_users;
		}
		$existing_user = $new_user;
		$username      = $new_user['username'];

		//
		// Does this user already exist? If so use them instead, and overwrite
		// some fields.
		if (array_key_exists($username, $existing_users)) {
			$existing_user = $existing_users[$username];
			if (empty($existing_user['alt_email'])) {
				$existing_user['alt_email'] = $new_user['alt_email'];
			}
			$existing_user['campus'] = array_unique(array_merge($new_user['campus'], $existing_user['campus']));
		}
		$existing_users[$username] = $existing_user;
		return $existing_users;
	}
}
