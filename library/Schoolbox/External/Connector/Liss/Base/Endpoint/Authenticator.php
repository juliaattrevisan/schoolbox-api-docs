<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use External\IConnector;
use PhpXmlRpc\Value;
use Psr\Log\LoggerInterface;

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
     * Default logging message when attempting to use liss with a non-schoolbox connector
     */
    private const EXTERNAL_DB_MESSAGE = 'External Database must be activated; exiting';


    /**
     * Whether the external db was enabled
     *
     * @var int
     */
    private int $externalEnable;


    /**
     * Username
     *
     * @var string
     */
    private string $username;

    /**
     * Password
     *
     * @var string
     */
    private string $password;


    /**
     * Log location
     *
     * @var LoggerInterface $log
     */
    private LoggerInterface $log;


    /**
     * An object containing username/password sent to us
     *
     * @var Value
     */
    private Value $dataObject;


    /**
     * Constructor.
     *
     * @param int             $externalEnable
     * @param string          $externalType
     * @param string          $username
     * @param string          $password
     * @param LoggerInterface $log
     * @param Value           $dataObject
     */
    public function __construct(
        int $externalEnable,
        string $externalType,
        string $username,
        string $password,
        LoggerInterface $log,
        Value $dataObject
    ) {
        $this->externalEnable = $externalEnable;
        $this->externalType = $externalType;
        $this->username = $username;
        $this->password = $password;
        $this->dataObject = $dataObject;
        $this->log = $log;
    }


    /**
     * Determine whether the request contains a valid
     * authentication object (that is always 0th element)
     */
    public function isAuthenticated(): bool
    {
        /* Must be using external database
         */
        $externalEnable = (bool)$this->externalEnable;

        if (!$externalEnable) {
            $this->log->info(self::EXTERNAL_DB_MESSAGE);

            return false;
        }

        /* Check the username/password tuple
         */
        $struct = $this->dataObject->me['struct'];

        $username = (string)$struct['UserName']->me['string'];
        $password = (string)$struct['Password']->me['string'];

        $isUsernameCorrect = $username === $this->username;
        $isPasswordCorrect = $password === $this->password;

        /* external_enable must be truthy
         * external_type must be "schoolbox"
         * (username, password) tuple must match what's transmitted
         */
        if ($isUsernameCorrect && $isPasswordCorrect) {
            $this->log->info(sprintf(
                'User %s has been authenticated',
                $username,
            ));

            return true;
        }

        $this->log->error(sprintf(
            'User %s has failed to authenticate',
            $username
        ));

        return false;
    }
}
