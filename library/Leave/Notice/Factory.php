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
    private Leave_Request $requestData;

    /**
     * Constructor
     *
     * @param Leave_Request $requestData
     */
    public function __construct(Leave_Request $requestData)
    {
        $this->requestData = $requestData;
    }

    /**
     * Produce a Parent factory
     *
     * @return Leave_Notice_Abstract_Factory
     */
    public function produceParentFactory(): Leave_Notice_Abstract_Factory
    {
        return new Leave_Core_Notice_Parent_Factory($this->requestData);
    }

    /**
     * Produce a User factory
     *
     * @return Leave_Notice_Abstract_Factory
     */
    public function produceUserFactory(): Leave_Notice_Abstract_Factory
    {
        return new Leave_Core_Notice_User_Factory($this->requestData);
    }

    /**
     * Produce an Admin factory
     *
     * @return Leave_Notice_Abstract_Factory
     */
    public function produceAdminFactory(): Leave_Notice_Abstract_Factory
    {
        return new Leave_Core_Notice_Admin_Factory($this->requestData);
    }
}
