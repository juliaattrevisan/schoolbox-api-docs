<?php

/**
 * Leave_Notice_Abstract_Notice
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Leave_Notice_Abstract_Notice
{
    protected $request = null;
    protected string $emailTemplateFile = '';

    /**
     * Constructor
     *
     * @param Leave_Request $request
     */
    public function __construct(Leave_Request $request)
    {
        $this->setRequest($request);
    }

    abstract public function setup();
    abstract public function send();

    /* Getters/Setters
     */

    /**
     * Set request
     *
     * @param Leave_Request $request
     */
    private function setRequest($request)
    {
        $this->request = $request;
    }

    /**
     * Get request
     *
     * @return Leave_Request
     */
    protected function getRequest()
    {
        return $this->request;
    }

    /**
     * Get email template file
     *
     * @return string
     */
    protected function getEmailTemplateFile()
    {
        return $this->emailTemplateFile;
    }
}
