<?php
/**
 * Base sync for all users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

abstract class Synchronise_Base {

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
	protected function createIfExists() {
		$this->verbose_filter->intro();
		foreach($this->list as $user) {
			$this->executeCreateUpdate($this->setupUserDetails($user));
		}
		$this->verbose_filter->summary();
	}

	/**
	 * Identical algorithm used to create/update a user
	 *
	 * @param  $newUser
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

	protected function userExists($user) {
		return $this->user_handler->getBySynergy($user['synergy_id']);
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
	 * Generates the list of users for sync, and jams them into $this->list.
	 *
	 * @return void
	 */
	public abstract function generateList();

	/**
	 * Creates an array for insertion into the Schoolbox users table.
	 *
	 * @param  array $external_user
	 *
	 * @return array
	 */
	public abstract function setupUserDetails($external_user);
}