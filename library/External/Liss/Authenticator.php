<?php

use Alaress\Lumberjack\Logger;

/**
 * Authenticator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class External_Liss_Authenticator
{

    /**
     * An object containing username/password sent to us
     *
     * @var null
     */
    private $dataObject = null;

    /**
     * Username
     *
     * @var string
     */
    private $username = 'alaress';

    /**
     * Password
     *
     * @var string
     */
    private $password = 'alaress';

    /**
     * School code
     *
     * @var string
     */
    private $schoolCode = 'EDVALHIGH';

    /**
     * Log location
     * @var Logger $log
     */
    private $log;

    /**
     * Constructor
     *
     * @param $dataObject
     * @param Logger $log
     */
    public function __construct(xmlrpcval $dataObject, Logger $log)
    {
        $this->setDataObject($dataObject);
        $this->setLog($log);
        $this->setup();
    }

    /**
     * Setup
     */
    protected function setup()
    {
        $this->setUsername(\Cfg::get('external_liss_username'));
        $this->setPassword(\Cfg::get('external_liss_password'));
        $this->setSchoolCode(\Cfg::get('external_liss_schoolcode'));
    }

    /**
     * Determine whether the request contains a valid
     * authentication object (that is always 0th element)
     */
    public function isAuthenticated()
    {
        $struct = $this->getDataObject()->me['struct'];

        $isUsernameCorrect = (string)$struct['UserName']->me['string'] == $this->getUsername();
        $isPasswordCorrect = (string)$struct['Password']->me['string'] == $this->getPassword();
        $isSchoolCorrect = (string)$struct['School']->me['string'] == $this->getSchoolCode();

        if ($isUsernameCorrect && $isPasswordCorrect && $isSchoolCorrect) {
            $this->getLog()->info(sprintf('User \'%s\' at school \'%s\' has been authenticated', (string)$struct['UserName']->me['string'], (string)$struct['School']->me['string']));
			return true;
		}
        $this->getLog()->error(sprintf('User \'%s\' at school \'%s\' has failed to authenticate',  (string)$struct['UserName']->me['string'], (string)$struct['School']->me['string']));
        return false;
    }

    /* Getters/Setters
     */

    /**
     * Set data object
     *
     * @param null $dataObject
     */
    private function setDataObject($dataObject)
    {
        $this->dataObject = $dataObject;
    }

    /**
     * Get data object
     *
     * @return null
     */
    private function getDataObject()
    {
        return $this->dataObject;
    }

    /**
     * Set password
     *
     * @param string $password
     */
    private function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * Get password
     *
     * @return string
     */
    private function getPassword()
    {
        return $this->password;
    }

    /**
     * Set username
     *
     * @param string $username
     */
    private function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * Get username
     *
     * @return string
     */
    private function getUsername()
    {
        return $this->username;
    }

    /**
     * Set school code
     *
     * @param string $schoolCode
     */
    private function setSchoolCode($schoolCode)
    {
        $this->schoolCode = $schoolCode;
    }

    /**
     * Get school code
     *
     * @return string
     */
    private function getSchoolCode()
    {
        return $this->schoolCode;
    }

    /**
     * @return Logger
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * @param Logger $log
     */
    public function setLog(Logger $log)
    {
        $this->log = $log;
    }


}
