<?php
/**
 * Class definition for Synergetic Synchronisation Strategies. Strategies are
 * individual classes that handle particular steps of an algorithm. This class
 * defines shared Strategy methods and shouldn't be instantiated, only extended
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class SynergeticSyncStrategy implements SynergeticSyncStrategyInterface {
	protected $userHandler;
	protected $groupHandler;
	protected $externalUserHandler;
	protected $performUpdate = false;
	protected $list = null;
	protected $strategyTitle = '';
	protected $verbose = true;

	/**
	 * Constructor
	 * Instantiate Handlers and things
	 */
	public function __construct() {
		$this->setUserHandler(new User());
		$this->setGroupHandler(new Group());
		$this->setExternalUserHandler(new ExternalUser());

		/* Set up ninja variable
		 */
		$this->getExternalUserHandler()->getCurrentSemester();
	}

	/**
	 * Collect a list of Users to add
	 *
	 * @param  $allUsers
	 * @return void
	 */
	protected function collateList($allUsers) {
		$users = array();

		if (is_array($allUsers) && count($allUsers)) {
			foreach($allUsers as $currentUser) {
				$users[] = $currentUser;
			}
		}

		$this->setList($users);
	}

	/**
	 * Print a nice heading before the rest of the confirmation messages
	 *
	 * @return void
	 */
	public function generateTitle() {
		echo "\n------------------------------------------------------------\n";
		echo $this->getStrategyTitle();
		echo "\n------------------------------------------------------------\n";
	}

	/**
	 * Prints out the actual or intended modification
	 *
	 * @param  $data
	 * @return void
	 */
	protected function generateVerboseResult($data) {
		if ($this->getPerformUpdate() === true) {
			$action = "Created account for: ";
		}
		else {
			$action = "Planned creation of: ";
		}

		$str = "$action [" . implode(', ', $data) . "]\n";

		print_r($str);
	}

	/* Generic getters and setters
	 */

	/**
	 * Set a list of people to process
	 *
	 * @param  $val
	 * @return void
	 */
	protected function setList($val) {
		$this->list = $val;
	}

	/**
	 * Get a list of people to process
	 *
	 * @return null
	 */
	protected function getList() {
		return $this->list;
	}

	/**
	 * Set the User handler
	 *
	 * @param  $val
	 * @return void
	 */
	private function setUserHandler($val) {
		$this->userHandler = $val;
	}

	/**
	 * Get the User handler
	 *
	 * @return
	 */
	protected function getUserHandler() {
		return $this->userHandler;
	}

	/**
	 * Set the Group handler
	 *
	 * @param  $val
	 * @return void
	 */
	private function setGroupHandler($val) {
		$this->groupHandler = $val;
	}

	/**
	 * Get the Group handler
	 *
	 * @return
	 */
	protected function getGroupHandler() {
		return $this->groupHandler;
	}

	/**
	 * Set the External User handler (this could be either Synergetic or Tass)
	 *
	 * @param  $val
	 * @return void
	 */
	private function setExternalUserHandler($val) {
		$this->externalUserHandler = $val;
	}

	/**
	 * Get the External User handler
	 *
	 * @return
	 */
	protected function getExternalUserHandler() {
		return $this->externalUserHandler;
	}

	/**
	 * Set the Perform Update flag
	 *
	 * @param  $val
	 * @return void
	 */
	public function setPerformUpdate($val) {
		$this->performUpdate = $val;
	}

	/**
	 * Get the Perform Update flag
	 *
	 * @return bool
	 */
	protected function getPerformUpdate() {
		return $this->performUpdate;
	}

	/**
	 * Get the title of the current Strategy
	 *
	 * @return string
	 */
	public function getStrategyTitle() {
		return $this->strategyTitle;
	}

	/**
	 * Set Verbose estting
	 *
	 * @param  $val
	 * @return void
	 */
	public function setVerbose($val) {
		$this->verbose = $val;
	}

	/**
	 * Get Verbose setting
	 *
	 * @return bool
	 */
	public function getVerbose() {
		return $this->verbose;
	}
}