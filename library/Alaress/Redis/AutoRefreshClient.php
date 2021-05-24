<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Redis;

use Predis\Client;
use Predis\Command\CommandInterface;
use Predis\CommunicationException;

class AutoRefreshClient extends Client
{

    const MAX_ATTEMPTS = 3;

    /**
     * Defer to recursive attempt function
     *
     * @param CommandInterface $command
     * @return mixed
     */
    public function executeCommand(CommandInterface $command)
    {
        return $this->tryCommand($command, self::MAX_ATTEMPTS);
    }


    /**
     * @param CommandInterface $command
     * @param int              $retries
     * @return mixed
     * @throws CommunicationException
     */
    protected function tryCommand(CommandInterface $command, $retries = 0)
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
