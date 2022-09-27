<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss;

use Alaress\Lumberjack\Logger;
use PhpXmlRpc\Value;

/**
 * Class Authenticator
 *
 * Checks if your liss settings match what was transmitted by the
 * liss client at the school, rejecting a request if the check fails
 *
 * (username, password, schoolCode) tuple is required to succeed
 */
class Authenticator
{
    /**
     * Username
     *
     * @var string|null
     */
    private ?string $username = null;

    /**
     * Password
     *
     * @var string|null
     */
    private ?string $password = null;

    /**
     * School code
     *
     * @var string|null
     */
    private ?string $schoolCode = null;


    /**
     * Log location
     * @var Logger $log
     */
    private Logger $log;


    /**
     * An object containing username/password sent to us
     *
     * @var Value
     */
    private Value $dataObject;


    /**
     * Constructor.
     *
     * @param string $username
     * @param string $password
     * @param string $schoolCode
     * @param Logger $log
     * @param Value  $dataObject
     */
    public function __construct(
        string $username,
        string $password,
        string $schoolCode,
        Logger $log,
        Value $dataObject
    ) {
        $this->username = $username;
        $this->password = $password;
        $this->schoolCode = $schoolCode;
        $this->dataObject = $dataObject;
        $this->log = $log;
    }


    /**
     * Determine whether the request contains a valid
     * authentication object (that is always 0th element)
     */
    public function isAuthenticated()
    {
        $struct = $this->dataObject->me['struct'];

        $isUsernameCorrect = (string)$struct['UserName']->me['string'] === $this->username;
        $isPasswordCorrect = (string)$struct['Password']->me['string'] === $this->password;
        $isSchoolCodeCorrect = (string)$struct['School']->me['string'] === $this->schoolCode;

        /* (username, password, schoolCode) tuple must match what's transmitted
         */
        if ($isUsernameCorrect && $isPasswordCorrect && $isSchoolCodeCorrect) {
            $this->log->info(sprintf(
                'User %s at school %s has been authenticated',
                (string)$struct['UserName']->me['string'],
                (string)$struct['School']->me['string']
            ));

            return true;
        }

        $this->log->error(sprintf(
            'User %s at school %s has failed to authenticate',
            (string)$struct['UserName']->me['string'],
            (string)$struct['School']->me['string']
        ));

        return false;
    }
}
