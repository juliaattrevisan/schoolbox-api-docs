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
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Leave_Notice_Factory
{
    private $requestData = null;
    private $classname   = '';

    /**
     * Constructor
     *
     * @param Leave_Request $requestData
     */
    public function __construct($requestData)
    {
        $this->setRequestData($requestData);
        $this->setClassname(ucfirst(strtolower(Config::get('site_school_code'))));
    }

    /**
     * Produce a Parent factory
     *
     * @return Leave_Notice_Abstract_Factory
     */
    public function produceParentFactory(): Leave_Notice_Abstract_Factory
    {
        return new Leave_Core_Notice_Parent_Factory($this->getRequestData());
    }

    /**
     * Produce a User factory
     *
     * @return Leave_Notice_Abstract_Factory
     */
    public function produceUserFactory(): Leave_Notice_Abstract_Factory
    {
        return new Leave_Core_Notice_User_Factory($this->getRequestData());
    }

    /**
     * Produce an Admin factory
     *
     * @return Leave_Notice_Abstract_Factory
     */
    public function produceAdminFactory(): Leave_Notice_Abstract_Factory
    {
        return new Leave_Core_Notice_Admin_Factory($this->getRequestData());
    }

    /* Getters/Setters
     */

    /**
     * Set classname
     *
     * @param string $classname
     */
    private function setClassname($classname)
    {
        $this->classname = $classname;
    }

    /**
     * Get classname
     *
     * @return string
     */
    private function getClassname()
    {
        return $this->classname;
    }

    /**
     * Set request data
     *
     * @param Leave_Request $requestData
     */
    private function setRequestData($requestData)
    {
        $this->requestData = $requestData;
    }

    /**
     * Get request data
     *
     * @return Leave_Request
     */
    private function getRequestData()
    {
        return $this->requestData;
    }
}
