<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Redis;

use Predis\Command\CommandInterface;
use Predis\CommunicationException;

/**
 * Wrapper around a Redis client, which attempts a command multiple times before
 * giving up.
 */
class AutoRefreshClientWrapper extends ClientWrapper
{
    private const int MAX_ATTEMPTS = 3;

    /**
     * Defer to recursive attempt function
     *
     * @param CommandInterface $command
     * @return mixed
     */
    public function executeCommand(CommandInterface $command): mixed
    {
        return $this->tryCommand($command, self::MAX_ATTEMPTS);
    }

    /**
     * @param CommandInterface $command
     * @param int              $retries
     * @return mixed
     * @throws CommunicationException
     */
    private function tryCommand(CommandInterface $command, int $retries = 0): mixed
    {
        try {
            return parent::executeCommand($command);
        } catch (CommunicationException $e) {
            // redis timed out, this attempt should have reawoken it
            if ($retries <= 0) {
                // if we've run out of retries, rethrow the exception
                throw $e;
            }

            // recurse
            return $this->tryCommand($command, $retries - 1);
        }
    }
}
