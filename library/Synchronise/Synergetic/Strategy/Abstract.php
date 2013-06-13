<?php
/**
 * Synchronise_Synergetic_Strategy_Abstract
 *
 * Class definition for Synergetic Synchronisation Strategies. Strategies are
 * individual classes that handle particular steps of an algorithm. This class
 * defines shared Strategy methods and shouldn't be instantiated, only extended
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Synchronise_Synergetic_Strategy_Abstract implements Synchronise_Synergetic_Strategy_Interface {
    /**
     * @var User
     */
    protected $userHandler;
	protected $groupHandler;
	protected $externalUserHandler;
	protected $performUpdate = false;
	protected $list = null;
	protected $strategyTitle = '';
	protected $verboseFilter = true;

	/**
	 * Constructor
	 * Instantiate Handlers and things
	 */
	public function __construct() {
		$this->setUserHandler(new User());
		$this->setGroupHandler(new Group());
		$this->setExternalUserHandler(new External_User());

		/* Set up ninja variable
		 */
		$this->getExternalUserHandler()->getCurrentSemester();

		/* Optional setup hook
		 * Does nothing by default so established sync scripts shouldn't be affected
		 */
		$this->setup();
	}

	/**
	 * Override this optionally
	 */
	protected function setup() {
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

	/* Getters/Setters
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
	 * @return User
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
	 * @return Group
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
	 * @return External_UserInterface
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
	 * Set verbose filter, the class to handle verbose messages
	 *
	 * @param  $val
	 * @return void
	 */
	protected function setVerboseFilter($val) {
		$this->verboseFilter = $val;
	}

	/**
	 * Get verbose filter
	 *
	 * @return Synchronise_Synergetic_Verbose_Abstract
	 */
	public function getVerboseFilter() {
		return $this->verboseFilter;
	}
}
