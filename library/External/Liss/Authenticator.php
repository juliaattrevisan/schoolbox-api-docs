<?php

use Alaress\Lumberjack\Logger;

use Schoolbox\IConfigLoader;

/**
 * Authenticator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
     * @var IConfigLoader
     */
    private $configLoader;

    /**
     * External_Liss_Authenticator constructor.
     *
     * @param xmlrpcval     $dataObject
     * @param Logger        $log
     * @param IConfigLoader $configLoader
     */
    public function __construct(xmlrpcval $dataObject, Logger $log, IConfigLoader $configLoader)
    {
        $this->setDataObject($dataObject);
        $this->setLog($log);
        $this->setConfigLoader($configLoader);

        $this->setup();
    }

    /**
     * Setup
     */
    protected function setup()
    {
        $this->setUsername($this->getConfigLoader()->get('external_liss_username'));
        $this->setPassword($this->getConfigLoader()->get('external_liss_password'));
        $this->setSchoolCode($this->getConfigLoader()->get('external_liss_schoolcode'));
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

        if ($isUsernameCorrect && $isPasswordCorrect) {
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
     * @return $this
     */
    private function setPassword($password)
    {
        $this->password = $password;
        return $this;
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
     * @return $this
     */
    private function setUsername($username)
    {
        $this->username = $username;
        return $this;
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
     * @return $this
     */
    private function setSchoolCode($schoolCode)
    {
        $this->schoolCode = $schoolCode;
        return $this;
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
     * @return $this
     */
    public function setLog(Logger $log)
    {
        $this->log = $log;
        return $this;
    }

    /**
     * @return IConfigLoader
     */
    public function getConfigLoader()
    {
        return $this->configLoader;
    }

    /**
     * @param IConfigLoader $configLoader
     * @return $this
     */
    public function setConfigLoader($configLoader)
    {
        $this->configLoader = $configLoader;
        return $this;
    }
}
