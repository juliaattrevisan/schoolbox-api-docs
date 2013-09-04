<?php
/**
 * Base sync for all users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

abstract class Synchronise_Base_Strategy {

	const DEFAULT_ADMIN_GROUP   = 1;
	const DEFAULT_STAFF_GROUP   = 2;
	const DEFAULT_STUDENT_GROUP = 3;
	const DEFAULT_PARENT_GROUP  = 4;

	/**
	 * Are updates performed? If not, then a plan is output instead.
	 *
	 * @var bool
	 */
	protected $perform_update = false;

	/**
	 * @var User
	 */
	protected $user_handler;

	/**
	 * @var Group
	 */
	protected $group_handler;

	/**
	 * @var External_User
	 */
	protected $external_user_handler;

	/**
	 * List of users to enter into Schoolbox.
	 *
	 * @var array
	 */
	protected $list = array();

	/**
	 * @var Synchronise_Base_Verbose
	 */
	protected $verbose_filter;

	/**
	 * The title for the synchronisation, output when the sync is run.
	 *
	 * @var string
	 */
	protected $title = "Update Schoolbox Parent Details Synchronisation";

	/**
	 * Constructor
	 * Instantiate Handlers and things
	 */
	public function __construct() {
		//
		// Set up stupid handlers
		$this->user_handler          = new User();
		$this->group_handler         = new Group();
		$this->external_user_handler = new External_User();

		//
		// Set up current_semester and current_year BS
		$this->external_user_handler->getCurrentSemester();

		//
		// Set up a base verbose filter, which you can override in setup()
		$this->verbose_filter = new Synchronise_Base_Verbose();
		$this->setup();
	}

	/**
	 * Setup hook for this synchroniser, after __construct is called.
	 * By default does nothing.
	 */
	protected function setup() {}

	/**
	 * If the users exist in Synergy but not in Schoolbox, create an account for
	 * them
	 *
	 * @return void
	 */
	public function createIfExists() {
		$this->verbose_filter->intro();
		foreach($this->list as $user) {
			$this->executeCreateUpdate($this->setupUserDetails($user));
		}
		$this->verbose_filter->summary();
	}

	/**
	 * Identical algorithm used to create/update a user
	 *
	 * @param  array $newUser The record that is to be added
	 * @return void
	 */
	protected function executeCreateUpdate($newUser) {
		$this->verbose_filter->setNewUser($newUser);

		//
		// Does this person have a username? If not, skip them
		if ($this->hasUsername($newUser)) {
			$existing = $this->userExists($newUser);

			//
			// If the current user exists in Schoolbox, update them
			if ($existing) {
				if ($this->perform_update) {
					$newUser['id'] = $existing['uid'];
					$this->user_handler->update($newUser);
					$this->verbose_filter->performUpdate();
				}
				else {
					$this->verbose_filter->planUpdate();
				}
				$this->verbose_filter->incrementUpdated();
			}
			else {
				//
				// Perform action; report what happened
				if ($this->perform_update) {
					$this->user_handler->create($newUser);
					$this->verbose_filter->performCreation();
				}
				else {
					$this->verbose_filter->planCreation();
				}
				$this->verbose_filter->incrementCreated();
			}
		}
		else {
			$this->verbose_filter->skip();
			$this->verbose_filter->incrementSkipped();
		}
	}

	/**
	 * Determine if this person has a username
	 *
	 * @param  $currentUser
	 * @return bool
	 */
	protected function hasUsername($currentUser) {
		return strlen(trim($currentUser['username'])) > 0;
	}

	/**
	 * Retrieves the user that exists in Schoolbox for this user account, or
	 * an empty value if they don't.
	 *
	 * @param  array c$user
	 *
	 * @return array
	 */
	protected function userExists($user) {
		return $this->user_handler->getBySynergy($user['synergy_id']);
	}

	/**
	 * @param  \Synchronise_Base_Verbose $verbose_filter
	 * @return $this
	 */
	public function setVerboseFilter($verbose_filter) {
		$this->verbose_filter = $verbose_filter;
		return $this;
	}

	/**
	 * @return \Synchronise_Base_Verbose
	 */
	public function getVerboseFilter() {
		return $this->verbose_filter;
	}

	/**
	 * @param  bool  $perform_update
	 * @return $this
	 */
	public function setPerformUpdate($perform_update) {
		$this->perform_update = $perform_update;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function getPerformUpdate() {
		return $this->perform_update;
	}

	/**
	 * Print a nice heading before the rest of the confirmation messages
	 *
	 * @return void
	 */
	public function generateTitle() {
		echo "\n------------------------------------------------------------\n";
		echo $this->title;
		echo "\n------------------------------------------------------------\n";
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
		return $currentUser['NetworkLogin'];
	}

	/**
	 * Gets Schoolbox user's title (Mr., Ms., etc.) from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getTitleFromUser($currentUser) {
		return '';
	}

	/**
	 * Gets Schoolbox user's first name from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getFirstNameFromUser($currentUser) {
		return '';
	}

	/**
	 * Gets Schoolbox user's last name from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getLastNameFromUser($currentUser) {
		return '';
	}

	/**
	 * Gets Schoolbox user's external database ID from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getExternalIdFromUser($currentUser) {
		return '';
	}

	/**
	 * Gets Schoolbox user's alternate email address from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return string
	 */
	public function getAltEmailFromUser($currentUser) {
		return null;
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
	 * Gets student's year from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getYearFromUser($currentUser) {
		return null;
	}

	/**
	 * Gets Schoolbox group from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getGroupIDFromUser($currentUser) {
		return 1;
	}

	/**
	 * Gets whether user is a superuser from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return bool
	 */
	public function getIsSuperuserFromUser($currentUser) {
		return false;
	}

	/**
	 * Gets user's house ID from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getHouseFromUser($currentUser) {
		return 0;
	}

	/**
	 * Gets user's residential house ID from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return int
	 */
	public function getResidentialHouseFromUser($currentUser) {
		return 0;
	}

	/**
	 * Gets user's eportfolio status from the external data set.
	 *
	 * @param  array $currentUser
	 *
	 * @return bool
	 */
	public function getEportfolioEnabledFromUser($currentUser) {
		return true;
	}

	/**
	 * Creates an array for insertion into the Schoolbox users table.
	 *
	 * @param  array $external_user
	 *
	 * @return array
	 */
	public function setupUserDetails($external_user) {
		$newUser = array();
		$newUser['username']             = $this->getUsernameFromUser($external_user);
		$newUser['title']                = $this->getTitleFromUser($external_user);
		$newUser['firstname']            = $this->getFirstNameFromUser($external_user);
		$newUser['lastname']             = $this->getLastNameFromUser($external_user);
		$newUser['synergy_id']           = $this->getExternalIdFromUser($external_user);
		$newUser['alt_email']            = $this->getAltEmailFromUser($external_user);
		$newUser['local_email']          = $this->getLocalEmailFromUser($external_user);
		$newUser['year']                 = $this->getYearFromUser($external_user);
		$newUser['defaultCampus']        = $this->getDefaultCampusFromUser($external_user);
		$newUser['campus']               = $this->getCampusListFromUser($external_user);
		$newUser['group_id']             = $this->getGroupIDFromUser($external_user);
		$newUser['su']                   = $this->getIsSuperuserFromUser($external_user);
		$newUser['house']                = $this->getHouseFromUser($external_user);
		$newUser['residential_house_id'] = $this->getResidentialHouseFromUser($external_user);
		$newUser['eportfolio_enabled']   = $this->getEportfolioEnabledFromUser($external_user);
		return $this->clearFields($newUser);
	}

	/**
	 * Filters fields with a null value out of user details.
	 *
	 * @param  array $new_user
	 *
	 * @return array
	 */
	protected function clearFields($new_user) {
		return array_filter($new_user, function($v) { return isset($v); });
	}


	/**
	 * Abstract methods
	 */

	/**
	 * Generates the list of users for sync, and jams them into $this->list.
	 *
	 * @return void
	 */
	public abstract function generateList();
}