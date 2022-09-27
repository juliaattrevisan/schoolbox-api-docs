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
     * Arbitrary const that denotes we're on the schoolbox connector
     * TODO This might be found elsewhere, can't remember where
     */
    private const SCHOOLBOX_CONNECTOR = 'schoolbox';


    /**
     * Default logging message when attempting to use liss with a non-schoolbox connector
     */
    private const WRONG_CONNECTOR_MESSAGE = 'External Database must be activated and the selected connector must be schoolbox; exiting';


    /**
     * Whether the external db was enabled
     *
     * @var int|null
     */
    private ?int $externalEnable = null;


    /**
     * Whether the external db is the "schoolbox" connector
     * or something else (it only works with "schoolbox" connector)
     *
     * @var string|null
     */
    private ?string $externalType = null;


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
     * @param int    $externalEnable
     * @param string $externalType
     * @param string $username
     * @param string $password
     * @param string $schoolCode
     * @param Logger $log
     * @param Value  $dataObject
     */
    public function __construct(
        int $externalEnable,
        string $externalType,
        string $username,
        string $password,
        string $schoolCode,
        Logger $log,
        Value $dataObject
    ) {
        $this->externalEnable = $externalEnable;
        $this->externalType = $externalType;
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
    public function isAuthenticated(): bool
    {
        /* Must be using external database and schoolbox connector
         */
        $externalEnable = (bool)$this->externalEnable;
        $externalType = self::SCHOOLBOX_CONNECTOR === $this->externalType;

        if (!($externalEnable && $externalType)) {
            $this->log->info(self::WRONG_CONNECTOR_MESSAGE);
            return false;
        }

        /* Check the username/passwor/schoolcode tuple
         */
        $struct = $this->dataObject->me['struct'];

        $username = (string)$struct['UserName']->me['string'];
        $schoolCode = (string)$struct['School']->me['string'];
        $password = (string)$struct['Password']->me['string'];

        $isUsernameCorrect = $username === $this->username;
        $isPasswordCorrect = $password === $this->password;
        $isSchoolCodeCorrect = $schoolCode === $this->schoolCode;

        /* external_enable must be truthy
         * external_type must be "schoolbox"
         * (username, password, schoolCode) tuple must match what's transmitted
         */
        if ($isUsernameCorrect && $isPasswordCorrect && $isSchoolCodeCorrect) {
            $this->log->info(sprintf(
                'User %s at school %s has been authenticated',
                $username,
                $schoolCode
            ));

            return true;
        }

        $this->log->error(sprintf(
            'User %s at school %s has failed to authenticate',
            $username,
            $schoolCode
        ));

        return false;
    }
}
