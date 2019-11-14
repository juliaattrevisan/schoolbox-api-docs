<?php
/**
 * Leave_Notice_Factory
 *
 * A factory which produces other factories.
 *
 * This factory produces one of the Leave_Notice_<school>_Parent or Leave_Notice_<school>_User factories.
 * These decide which email templates to use in their notifications and controls their various behaviours
 *
 * This factory encapsulates site/domain detection, taking it out of the controller.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link		https://schoolbox.com.au/
 */
class Leave_Notice_Factory {
	private $requestData = null;
	private $classname = '';

	/**
	 * Constructor
	 *
	 * @param $requestData
	 */
	public function __construct($requestData) {
		$this->setRequestData($requestData);
		$this->setClassname(ucwords(Config::get('site_location')));
	}

	/**
	 * Produce a Parent factory
	 *
	 * @return mixed
	 */
	public function produceParentFactory() {
		$fullClassname = "Leave_{$this->getClassname()}_Notice_Parent_Factory";

        if(!class_exists($fullClassname)) {
            $fullClassname = "Leave_Core_Notice_Parent_Factory";
        }

        return new $fullClassname($this->getRequestData());
	}

	/**
	 * Produce a User factory
	 *
	 * @return mixed
	 */
	public function produceUserFactory() {
		$fullClassname = "Leave_{$this->getClassname()}_Notice_User_Factory";

        if(!class_exists($fullClassname)) { //Default to core
            $fullClassname = "Leave_Core_Notice_User_Factory";
        }

        return new $fullClassname($this->getRequestData());
	}

    /**
     * Produce an Admin factory
     *
     * @return mixed
     */
    public function produceAdminFactory() {
        $fullClassname = "Leave_{$this->getClassname()}_Notice_Admin_Factory";

        if(!class_exists($fullClassname)) { //Default to core
            $fullClassname = "Leave_Core_Notice_Admin_Factory";
        }

        return new $fullClassname($this->getRequestData());
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
	 * Set request data
	 *
	 * @param $requestData
	 */
	private function setRequestData($requestData) {
		$this->requestData = $requestData;
	}

	/**
	 * Get request data
	 *
	 * @return null
	 */
	private function getRequestData() {
		return $this->requestData;
	}
}
