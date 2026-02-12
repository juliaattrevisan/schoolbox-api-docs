<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Redis;

use Alaress\Sentry\Tracing\Tracer;
use Predis\Client;
use Predis\Command\CommandInterface;
use Predis\CommunicationException;

/**
 * Extension of Redis client, which:
 * * attempts a command multiple times before giving up
 * * traces
 */
class AutoRefreshClient extends Client
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
        // Data from https://opentelemetry.io/docs/specs/semconv/db/redis/
        $queryText = $command->getId() . ' ' . implode(' ', $command->getArguments());
        return Tracer::getDefaultTrace()->runWithinSpan(
            fn () => $this->tryCommand($command, self::MAX_ATTEMPTS),
            $queryText,
            'db.redis',
            'auto.db.redis',
            [
                'db.system.name'    => 'redis',
                'db.operation.name' => $command->getId(),
                'db.namespace'      => $this->getConnection()->getParameters()->database,
                'server.address'    => $this->getConnection()->getParameters()->host,
                'server.port'       => $this->getConnection()->getParameters()->port,
                'db.query.text'     => $queryText,
            ],
        );

        // The following spans aren't able to be provided:
        // db.response.status_code, error.type: not known until command runs, runWithinSpan doesn't have a callback yet
        //                                      to record it
        // db.operation.batch.size:             batch is not known
        // db.stored_procedure.name:            stored proc is not known
        // network.peer.{address,port}:         how different to server.{address,port}?
    }

    /**
     * @param CommandInterface $command
     * @param int              $retries
     * @return mixed
     * @throws CommunicationException
     */
    protected function tryCommand(CommandInterface $command, int $retries = 0): mixed
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
