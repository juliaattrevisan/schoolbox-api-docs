<?php
/**
 * Leave_Report_House_Factory
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Leave_Report_House_Factory {
	private $page = null;
	private $data = array();
	private $range = -1;
	private $classname = '';

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $data
	 * @param $range
	 */
	public function __construct($page, $data, $range) {
		$this->setPage($page);
		$this->setData($data);
		$this->setRange($range);
		$this->setClassname(ucwords(Cfg::get('site_location')));
	}

	/**
	 * Produce a CSV report instance
	 *
	 * @return mixed
	 */
	public function produceCSV() {
		return $this->produce("Leave_{$this->getClassname()}_Report_House_CSV");
	}

	/**
	 * Produce a print report instance
	 *
	 * @return mixed
	 */
	public function producePrint() {
		return $this->produce("Leave_{$this->getClassname()}_Report_House_Print");
	}

	/**
	 * Produce a web report instance
	 *
	 * @return mixed
	 */
	public function produceWeb() {
		return $this->produce("Leave_{$this->getClassname()}_Report_House_Web");
	}

	/**
	 * Produce class with required data
	 *
	 * @param $fullClassname
	 * @return mixed
	 */
	private function produce($fullClassname) {
		return new $fullClassname($this->getPage(), $this->getData(), $this->getRange());
	}

	/* Getters/Setters
	 */

	/**
	 * Set classname
	 *
	 * @param $classname
	 */
	private function setClassname($classname) {
		$this->classname = $classname;
	}

	/**
	 * Get classname
	 *
	 * @return string
	 */
	private function getClassname() {
		return $this->classname;
	}

	/**
	 * Set data
	 *
	 * @param $data
	 */
	private function setData($data) {
		$this->data = $data;
	}

	/**
	 * Get data
	 *
	 * @return array
	 */
	private function getData() {
		return $this->data;
	}

	/**
	 * Set page
	 *
	 * @param $page
	 */
	private function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	private function getPage() {
		return $this->page;
	}

	/**
	 * Set range
	 *
	 * @param $range
	 */
	private function setRange($range) {
		$this->range = $range;
	}

	/**
	 * Get range
	 *
	 * @return int
	 */
	private function getRange() {
		return $this->range;
	}
}
