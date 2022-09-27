<?php

use Alaress\Lumberjack\Logger;
use PhpXmlRpc\Value;
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
     * @var Value
     */
    private Value $dataObject;

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
     * @param Value         $dataObject
     * @param Logger        $log
     * @param IConfigLoader $configLoader
     */
    public function __construct(Value $dataObject, Logger $log, IConfigLoader $configLoader)
    {
        $this->dataObject   = $dataObject;
        $this->log          = $log;
        $this->configLoader = $configLoader;
        $this->setup();
    }

    /**
     * Setup
     */
    protected function setup()
    {
        $this->username   = $this->configLoader->get('external_liss_username');
        $this->password   = $this->configLoader->get('external_liss_password');
        $this->schoolCode = $this->configLoader->get('external_liss_schoolcode');
    }

    /**
     * Determine whether the request contains a valid
     * authentication object (that is always 0th element)
     */
    public function isAuthenticated()
    {
        $struct = $this->dataObject->me['struct'];
$this->log->info(json_encode($struct));die();
        $isUsernameCorrect = (string)$struct['UserName']->me['string'] == $this->username;
        $isPasswordCorrect = (string)$struct['Password']->me['string'] == $this->password;

        if ($isUsernameCorrect && $isPasswordCorrect) {
            $this->log->info(sprintf('User \'%s\' at school \'%s\' has been authenticated', (string)$struct['UserName']->me['string'], (string)$struct['School']->me['string']));
            return true;
        }

        $this->log->error(sprintf('User \'%s\' at school \'%s\' has failed to authenticate', (string)$struct['UserName']->me['string'], (string)$struct['School']->me['string']));

        return false;
    }
}
