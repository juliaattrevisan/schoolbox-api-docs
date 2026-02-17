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
use Schoolbox\Url\Uri;

/**
 * Extension of Redis client, which:
 * * attempts a command multiple times before giving up
 * * sends traces on connect and query to Sentry
 */
class AutoRefreshClient extends Client
{
    /**
     * Command executions may be attempted up to 3 times before being rejected.
     */
    private const int MAX_ATTEMPTS = 3;

    /**
     * Cached connection parameters.
     *
     * @var array{host: string, port:int|null, database: string, scheme: string}|null
     */
    private ?array $connectionParams = null;

    /**
     * Instrument connection.
     *
     * For some reason this is not called when connecting to Redis, though?
     * It needs to be called manually.
     *
     * @return void
     */
    public function connect(): void
    {
        $connectionParams = $this->getConnectionParams();
        $url = new Uri()
            ->withHost($connectionParams['host'])
            ->withPort($connectionParams['port'])
            ->withPath($connectionParams['database'])
            ->withScheme($connectionParams['scheme']);
        Tracer::getDefaultTrace()->runWithinSpan(
            parent::connect(...),
            sprintf('connect %s', $url->__toString()),
            'db.redis.connect',
            'auto.db',
            $this->getBaseSpanData(),
        );
    }

    /**
     * Defer to recursive attempt function
     *
     * @param CommandInterface $command
     * @return mixed
     */
    public function executeCommand(CommandInterface $command): mixed
    {
        // Need to connect if have not done so already:
        // this class' connect method is not called manually, I am guessing the
        // internal connection object's connect method is called.
        if (!$this->isConnected()) {
            $this->connect();
        }

        // Data from https://opentelemetry.io/docs/specs/semconv/db/redis/
        $queryText = sprintf(
            '%s %s',
            $command->getId(),
            implode(' ', array_map($this->formatForQueryText(...), $command->getArguments())),
        );
        return Tracer::getDefaultTrace()->runWithinSpan(
            fn () => $this->tryCommand($command, self::MAX_ATTEMPTS),
            $queryText,
            'db.redis',
            'auto.db.redis',
            $this->getBaseSpanData() + [
                'db.operation.name' => $command->getId(),
                'db.query.text'     => $queryText,
            ],
        );

        // The following spans aren't able to be provided:
        // db.response.status_code, error.type: not known until command runs, runWithinSpan doesn't have a callback yet
        //                                      to record it
        // db.operation.batch.size:             batch is not known
        // db.stored_procedure.name:            stored proc is not known
        // network.peer.{address,port}:         how different to server.{address,port}?
        //
        // Regarding errors:
        // The Redis client actually does not throw an exception on malformed
        // query, thus making it futile to attempt to determine what type of
        // error the last request returned.
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

    /**
     * Get connection parameters, caching if not yet fetched.
     *
     * @return array{host: string, port:int|null, database: string, scheme: string}
     */
    private function getConnectionParams(): array
    {
        return $this->connectionParams ??= [
            'host'     => $this->getConnection()->getParameters()->host ?? '',
            'port'     => $this->getConnection()->getParameters()->port ?? null,
            'database' => $this->getConnection()->getParameters()->database ?? '',
            'scheme'   => $this->getConnection()->getParameters()->scheme ?? '',
        ];
    }

    /**
     * Get base data for a Sentry span.
     *
     * @return array
     */
    private function getBaseSpanData(): array
    {
        $connectionParams = $this->getConnectionParams();
        return [
            'db.system'      => 'redis',
            'db.namespace'   => $connectionParams['database'],
            'server.address' => $connectionParams['host'],
            'server.port'    => $connectionParams['port'],
        ];
    }

    /**
     * Attempt to format whatever `$argument` is, for use in a Sentry span
     * description.
     *
     * @param mixed $argument
     * @return string
     */
    private function formatForQueryText(mixed $argument): string
    {
        if (is_bool($argument)) {
            return $argument ? 'true' : 'false';
        }
        if (is_scalar($argument)) {
            return (string)$argument;
        }
        if (is_array($argument)) {
            return sprintf('array{%d}', count($argument));
        }
        if (is_object($argument)) {
            return sprintf('object{%s}', get_class($argument));
        }
        return '';
    }
}
