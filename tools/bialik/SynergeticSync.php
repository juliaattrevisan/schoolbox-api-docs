<?php
/**
 * Class definition for Synergetic Synchronisation. Contains Strategies so you
 * can change the actual steps performed without altering the overall algorithm
 */
class SynergeticSync {
	private $strategy = null;

	/**
	 * Choose your strategy wisely
	 *
	 * @param  $strategy
	 * @param bool $performUpdate
	 */
	public function __construct($strategy = null) {
		if ($strategy !== null) {
			$this->setStrategy(new $strategy);
		}
	}

	/**
	 * Facade method to directly access Strategy from Sync context
	 *
	 * @param  $val
	 * @return void
	 */
	public function setPerformUpdate($val) {
		$this->getStrategy()->setPerformUpdate($val);
	}

	/**
	 * Create a list of records and process them all
	 *
	 * @return void
	 */
	public function synchronise() {
		$this->getStrategy()->generateList();
		$this->getStrategy()->createIfExists();
	}

	public function setStrategy($val) {
		$this->strategy = $val;
	}

	public function getStrategy() {
		return $this->strategy;
	}
}