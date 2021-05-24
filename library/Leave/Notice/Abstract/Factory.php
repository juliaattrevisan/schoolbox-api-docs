<?php

/**
 * Leave_Notice_Abstract_Factory
 *
 * Abstract Factory Method. This used by the _User and _Parent factories for notifications
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Leave_Notice_Abstract_Factory
{
    protected $request = null;

    /**
     * Constructor
     *
     * @param Leave_Request $request
     */
    public function __construct($request)
    {
        $this->setRequest($request);
    }

    /**
     * Produce a Notice object whose implementation is school-specific
     */
    abstract public function produce();

    /* Getters/Setters
     */

    /**
     * @param Leave_Request $request
     */
    protected function setRequest($request)
    {
        $this->request = $request;
    }

    /**
     * @return Leave_Request
     */
    protected function getRequest()
    {
        return $this->request;
    }
}
