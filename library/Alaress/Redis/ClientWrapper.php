<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Redis;

use Predis\ClientInterface;
use Predis\Command\CommandInterface;
use Predis\Command\FactoryInterface;
use Predis\Configuration\OptionsInterface;
use Predis\Connection\ConnectionInterface;

/**
 * Wrapper around a Redis client.
 *
 * Should be extended by other Redis clients, for special usages.
 *
 * Unfortunately must be shelved due to a vagary of Symfony's cache: it attempts
 * to re-construct an instance of whatever class it receives, and assumes it
 * accepts the same constructor parameters as `\Predis\Client`.
 */
class ClientWrapper implements ClientInterface
{
    /**
     * Constructor
     *
     * @param ClientInterface $client All calls to interface methods are passed
     *                                through to this interface.
     */
    public function __construct(private ClientInterface $client)
    {
    }

    // All interface implementations simply pass through to the client.

    public function getCommandFactory(): FactoryInterface
    {
        return $this->client->getCommandFactory();
    }

    public function getOptions(): OptionsInterface
    {
        return $this->client->getOptions();
    }

    public function connect(): void
    {
        $this->client->connect();
    }

    public function disconnect(): void
    {
        $this->client->disconnect();
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->client->getConnection();
    }

    public function createCommand($method, $arguments = []): CommandInterface
    {
        return $this->client->createCommand($method, $arguments);
    }

    public function executeCommand(CommandInterface $command): mixed
    {
        return $this->client->executeCommand($command);
    }

    public function __call($method, $arguments)
    {
        return call_user_func([$this->client, $method], ...$arguments);
    }
}
