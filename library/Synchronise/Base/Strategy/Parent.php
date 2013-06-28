<?php
/**
 * Synchronise_Base_Strategy_Parent
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */

class Synchronise_Base_Strategy_Parent extends Synchronise_Base_Strategy {
	protected $strategyTitle = "Update Schoolbox Parent Details Synchronisation";

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$this->list = $this->external_user_handler->getParentUserAccountInfo();
	}

	/**
	 * Set up the new user's spouse's details
	 *
	 * @param  array $external_user
	 * @return array
	 */
	public function setupSpouseDetails($external_user) {
		$newUser = array();
		$newUser['username']      = $this->getSpouseUsernameFromUser($external_user);
		$newUser['title']         = $this->getSpouseTitleFromUser($external_user);
		$newUser['firstname']     = $this->getSpouseFirstNameFromUser($external_user);
		$newUser['lastname']      = $this->getSpouseLastNameFromUser($external_user);
		$newUser['synergy_id']    = $this->getSpouseExternalIdFromUser($external_user);
		$newUser['alt_email']     = $this->getSpouseAltEmailFromUser($external_user);
		$newUser['local_email']   = $this->getSpouseLocalEmailFromUser($external_user);
		$newUser['year']          = $this->getSpouseYearFromUser($external_user);
		$newUser['defaultCampus'] = $this->getSpouseDefaultCampusFromUser($external_user);
		$newUser['campus']        = $this->getSpouseCampusListFromUser($external_user);
		$newUser['group_id']      = $this->getSpouseGroupIDFromUser($external_user);
		$newUser['su']            = $this->getSpouseIsSuperuserFromUser($external_user);
		return $this->clearFields($newUser);
	}


	/**
	 * If the users exist in Synergy but not in Schoolbox, create an account for
	 * them
	 *
	 * @return void
	 */
	public function createIfExists() {
		$this->verbose_filter->intro();

		//
		// Pass 1: get a unique list of users
		$parents = array();
		foreach ($this->list as $currentUser) {
			//
			// Get user and their spouse.
			$users = array(
				$this->setupUserDetails($currentUser),
				$this->setupSpouseDetails($currentUser),
			);
			foreach ($users as $newUser) {
				//
				// Add each user to the list of unique users, merging the campus
				// list into the new one.
				$user_id = $newUser['synergy_id'];
				if (!isset($parents[$user_id])) {
					$parents[$user_id] = $newUser;
				}
				//
				// Overwrite default campus
				$parents[$user_id]['defaultCampus'] = $newUser['campus'];
				$parents[$user_id]['campus'] = array_unique(array_merge(
						$parents[$user_id]['campus'],
						$newUser['campus']
					));
			}
		}

		//
		// Pass 2: insert/update into list of users
		foreach ($parents as $parent) {
			$this->executeCreateUpdate($parent);
		}

		$this->verbose_filter->summary();
	}


	/**
	 * Methods for retrieving Schoolbox fields from an external data set.
	 * These may be overridden on a per-sync basis.
	 */

	/**
	 * Gets Schoolbox username from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getUsernameFromUser($currentUser) {
		return $currentUser['StudentContactID'];
	}

	/**
	 * Gets Schoolbox user's title (Mr., Ms., etc.) from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getTitleFromUser($currentUser) {
		return $currentUser['StudentContactTitle'];
	}

	/**
	 * Gets Schoolbox user's first name from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getFirstNameFromUser($currentUser) {
		return $currentUser['StudentContactPreferred'];
	}

	/**
	 * Gets Schoolbox user's last name from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getLastNameFromUser($currentUser) {
		return $currentUser['StudentContactSurname'];
	}

	/**
	 * Gets Schoolbox user's external database ID from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getExternalIdFromUser($currentUser) {
		return $currentUser['StudentContactID'];
	}

	/**
	 * Gets Schoolbox user's alternate email address from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getAltEmailFromUser($currentUser) {
		return $currentUser['StudentContactDefaultEmail'];
	}

	/**
	 * Gets Schoolbox user's local email setting from the external data set.
	 *
	 * For most schools, this will be a school-wide setting instead of a
	 * per-user setting.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getLocalEmailFromUser($currentUser) {
		return 0;
	}

	/**
	 * Gets Schoolbox default campus from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getDefaultCampusFromUser($currentUser) {
		return 1;
	}

	/**
	 * Gets Schoolbox campus list from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return array[int]
	 */
	public function getCampusListFromUser($currentUser) {
		return array($this->getDefaultCampusFromUser($currentUser));
	}

	/**
	 * Gets Schoolbox group from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getGroupIDFromUser($currentUser) {
		return self::DEFAULT_PARENT_GROUP;
	}

	/**
	 * Gets this user's year from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getYearFromUser($currentUser) {
		return null;
	}

	/**
	 * Gets Schoolbox username from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getSpouseUsernameFromUser($currentUser) {
		return $currentUser['StudentContactSpouseID'];
	}

	/**
	 * Gets Schoolbox user's title (Mr., Ms., etc.) from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getSpouseTitleFromUser($currentUser) {
		return $currentUser['StudentContactSpouseTitle'];
	}

	/**
	 * Gets Schoolbox user's first name from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getSpouseFirstNameFromUser($currentUser) {
		return $currentUser['StudentContactSpousePreferred'];
	}

	/**
	 * Gets Schoolbox user's last name from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getSpouseLastNameFromUser($currentUser) {
		return $currentUser['StudentContactSpouseSurname'];
	}

	/**
	 * Gets Schoolbox user's external database ID from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getSpouseExternalIdFromUser($currentUser) {
		return $currentUser['StudentContactSpouseID'];
	}

	/**
	 * Gets Schoolbox user's alternate email address from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getSpouseAltEmailFromUser($currentUser) {
		return $currentUser['StudentContactSpouseDefaultEmail'];
	}

	/**
	 * Gets Schoolbox user's local email setting from the external data set.
	 *
	 * For most schools, this will be a school-wide setting instead of a
	 * per-user setting.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getSpouseLocalEmailFromUser($currentUser) {
		return 0;
	}

	/**
	 * Gets Schoolbox default campus from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getSpouseDefaultCampusFromUser($currentUser) {
		return 1;
	}

	/**
	 * Gets Schoolbox campus list from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return array[int]
	 */
	public function getSpouseCampusListFromUser($currentUser) {
		return array($this->getDefaultCampusFromUser($currentUser));
	}

	/**
	 * Gets Schoolbox group from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getSpouseGroupIDFromUser($currentUser) {
		return 1;
	}

	/**
	 * Gets this user's year from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getSpouseYearFromUser($currentUser) {
		return null;
	}

	/**
	 * Gets whether spouse is a superuser from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getSpouseIsSuperuserFromUser($currentUser) {
		return false;
	}
}