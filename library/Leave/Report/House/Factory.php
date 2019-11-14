<?php
/**
 * Leave_Report_House_Factory
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link		https://schoolbox.com.au/
 */
class Leave_Report_House_Factory {
	private $page;
	private $data;
	private $classname = '';

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $data
	 */
	public function __construct($page, $data) {
		$this->setPage($page);
		$this->setData($data);
		$this->setClassname(ucwords(Config::get('site_location')));
	}

	/**
	 * Produce a CSV report instance
	 *
	 * @return Leave_Report_House_RenderableInterface
	 */
	public function produceCSV() {
        $fullname = "Leave_{$this->getClassname()}_Report_House_CSV";
        if(!class_exists($fullname)) {
            $fullname = "Leave_Core_Report_House_CSV";
        }
		return $this->produce($fullname);
	}

	/**
	 * Produce a print report instance
	 *
	 * @return Leave_Report_House_RenderableInterface
	 */
	public function producePrint() {
        $fullname = "Leave_{$this->getClassname()}_Report_House_Print";
        if(!class_exists($fullname)) {
		   $fullname = "Leave_Core_Report_House_Print";
        }
        return $this->produce($fullname);
	}

	/**
	 * Produce a web report instance
	 *
	 * @return Leave_Report_House_RenderableInterface
	 */
	public function produceWeb() {
        $fullname = "Leave_{$this->getClassname()}_Report_House_Web";
        if(!class_exists($fullname)) {
            $fullname = "Leave_Core_Report_House_Web";
        }
		return $this->produce($fullname);
	}

	/**
	 * Produce class with required data
	 *
	 * @param $fullClassname
	 * @return mixed
	 */
	private function produce($fullClassname) {
		return new $fullClassname($this->getPage(), $this->getData());
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
}
