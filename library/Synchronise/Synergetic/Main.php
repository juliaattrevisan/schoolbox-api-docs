<?php
/**
 * Class definition for Synergetic Synchronisation. Contains Strategies so you
 * can change the actual steps performed without altering the overall algorithm
 * (Template Design Pattern, Strategy Design Pattern)
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Main {
	private $strategy = null;

	/**
	 * Choose your strategy wisely
	 *
	 * @param null $strategy
	 */
	public function __construct($strategy = null) {
		if ($strategy !== null) {
			$this->setStrategy(new $strategy);
		}
	}

	/**
	 * Delegation method for generating a title stored in the Strategy
	 *
	 * @return void
	 */
	public function generateTitle() {
		$this->getStrategy()->generateTitle();
	}

	/**
	 * Create a list of records and process them all
	 *
	 * @return void
	 */
	public function synchronise() {
		$this->getStrategy()->generateList();
		$this->getStrategy()->createIfExists();
        $this->getStrategy()->removeIfNotExists();
	}

	/**
	 * Delegation method to directly access Verbose from Sync context
	 *
	 * @param  $val
	 * @return void
	 */
	public function setVerbose($val) {
		$this->getStrategy()->getVerboseFilter()->setVerbose($val);
	}

	/**
	 * Delegation method to directly access Strategy from Sync context
	 *
	 * @param  $val
	 * @return void
	 */
	public function setPerformUpdate($val) {
		$this->getStrategy()->setPerformUpdate($val);
	}

	/**
	 * Set the Strategy
	 *
	 * @param  $val
	 * @return void
	 */
	public function setStrategy($val) {
		$this->strategy = $val;
	}

	/**
	 * Get the Strategy
	 *
	 * @return null
	 */
	public function getStrategy() {
		return $this->strategy;
	}
}