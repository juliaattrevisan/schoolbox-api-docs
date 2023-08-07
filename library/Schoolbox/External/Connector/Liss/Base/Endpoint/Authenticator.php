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
use SensitiveParameter;

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
    private const WRONG_CONNECTOR_MESSAGE = 'External Database must be activated and the selected connector must be schoolbox; exiting';


    /**
     * Whether the external db was enabled
     *
     * @var int
     */
    private int $externalEnable;


    /**
     * Whether the external db is the "schoolbox" connector
     * or something else (it only works with "schoolbox" connector)
     *
     * @var string
     */
    private string $externalType;


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
        #[SensitiveParameter] string $password,
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
     *
     * @param bool $requireSchoolboxConnector
     * @return bool
     */
    public function isAuthenticated(bool $requireSchoolboxConnector = true): bool
    {
        if ($requireSchoolboxConnector) {
            /* Must be using external database and schoolbox connector, USUALLY.
             * (except for publishDailyData which doesn't have this requirement)
             */
            $externalEnable = (bool)$this->externalEnable;
            $externalType   = IConnector::EXTERNAL_TYPE_SCHOOLBOX === $this->externalType;

            if (!($externalEnable && $externalType)) {
                $this->log->info(self::WRONG_CONNECTOR_MESSAGE);

                return false;
            }
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
