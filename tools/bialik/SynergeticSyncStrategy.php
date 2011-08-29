<?php
/**
 * Class definition for Synergetic Synchronisation Strategies
 */
abstract class SynergeticSyncStrategy implements SynergeticSyncStrategyInterface {
	protected $userHandler;
	protected $groupHandler;
	protected $externalUserHandler;
	protected $performUpdate = false;
	protected $list = null;
	protected $strategyTitle = '';

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
				return 'main';
				break;
			case '00':
			case '01':
			case '30':
			case '40':
				return 'elc';
				break;
			default:
				return false;
				break;
		}
	}

	/**
	 * Print a nice heading
	 *
	 * @return void
	 */
	public function generateTitle() {
		echo "\n-----------------------\n" . $this->getStrategyTitle() . "\n-----------------------\n";
	}

	/* Generic getters and setters
	 */

	protected function setList($val) {
		$this->list = $val;
	}

	protected function getList() {
		return $this->list;
	}

	private function setUserHandler($val) {
		$this->userHandler = $val;
	}

	protected function getUserHandler() {
		return $this->userHandler;
	}

	private function setGroupHandler($val) {
		$this->groupHandler = $val;
	}

	protected function getGroupHandler() {
		return $this->groupHandler;
	}

	private function setExternalUserHandler($val) {
		$this->externalUserHandler = $val;
	}

	protected function getExternalUserHandler() {
		return $this->externalUserHandler;
	}

	public function setPerformUpdate($val) {
		$this->performUpdate = $val;
	}

	protected function getPerformUpdate() {
		return $this->performUpdate;
	}

	public function getStrategyTitle() {
		return $this->strategyTitle;
	}
}