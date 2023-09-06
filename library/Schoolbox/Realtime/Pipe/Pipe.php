<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Pipe;

use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;

/**
 * This class encapsulates a Socket (pipe) and its associated information.
 */
class Pipe
{
    /**
     * Is this pipe locked, and if so by what command?
     */
    private ?string $commandLock = null;

    /**
     * The pipe's user ID, if one has been set yet.
     */
    protected ?int $userId = null;

    /**
     * The request's cookies, if loaded yet.
     */
    protected ?array $cookies = null;

    /**
     * Constructor
     *
     * @param ConnectionInterface $socketConnection The socket connection which created this pipe.
     * @param string              $instanceId       The instance ID this pipe was created for: derived from the hostname
     *                                              within the socket connection's HTTP request.
     */
    public function __construct(
        private ConnectionInterface $socketConnection,
        private string $instanceId,
    ) {
    }

    /**
     * Extract the socket key out of the given connection.
     *
     * @param ConnectionInterface $conn
     * @return string
     */
    public static function getSocketKeyForConnection(ConnectionInterface $conn): string
    {
        return static::getHeader($conn, 'sec-websocket-key');
    }

    /**
     * Returns the Sec-Websocket-Key header of this websocket: this is a unique
     * connection identifier.
     *
     * @return string
     */
    public function getSocketKey(): string
    {
        return self::getSocketKeyForConnection($this->socketConnection);
    }

    /**
     * Returns the given cookie of this websocket, if present.
     *
     * @param string $name
     * @return string
     */
    public function getCookie(string $name): string
    {
        // only do this once
        if (!isset($this->cookies)) {
            $cookieString = static::getHeader($this->socketConnection, 'cookie');

            preg_match_all('`([A-Za-z]+)=([^;]+)`', $cookieString, $matches);
            $this->cookies = array_combine($matches[1], $matches[2]);
        }

        return $this->cookies[$name] ?? '';
    }

    /**
     * Returns the session id of this websocket, if it has one.
     *
     * @return string
     */
    public function getSession(): string
    {
        return $this->getCookie(PipeManager::PHP_SESSION_PREFIX);
    }

    /**
     * Returns the remote address of the connection of this pipe.
     *
     * @return string
     */
    public function getRemoteAddress(): string
    {
        // kosher according to http://socketo.me/docs/migration-3
        /**
         * @noinspection PhpPossiblePolymorphicInvocationInspection
         * @phpstan-ignore-next-line
         */
        return $this->socketConnection->remoteAddress ?: '0.0.0.0';
    }


    /**
     * Close the pipe.
     *
     * @return void
     */
    public function close(): void
    {
        // TODO I am not sure whether this needs to happen if calling $this->socketConnection->close()
        // Explicitly close the descriptor to the temp file associated with the
        // request that initiated this connection.
        // Before this fix, we were running out of file descriptors because they
        // were never being released due to a bug in the vendor implementation.
        self::extractWebSocketRequest($this->socketConnection)->getBody()->close();

        // And close the socket connection itself
        $this->socketConnection->close();
    }


    /**
     * Send data through the pipe's socket connection.
     *
     * @param string $data
     * @return $this
     */
    public function send(string $data): self
    {
        $this->socketConnection->send($data);
        return $this;
    }

    /**
     * Fetch the instance ID for this pipe.
     *
     * @return string
     */
    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    /**
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * Set the user ID of the pipe, if any.
     *
     * @param int|null $id
     * @return $this
     */
    public function setUserId(?int $id)
    {
        $this->userId = $id;
        return $this;
    }

    /**
     * Extract a header out of the connection.
     *
     * @param ConnectionInterface $conn
     * @param string $header
     * @return string
     */
    protected static function getHeader(ConnectionInterface $conn, string $header): string
    {
        $rq = self::extractWebSocketRequest($conn);

        $value = $rq->getHeader($header);

        if (count($value) > 0) {
            return (string)reset($value);
        }
        // otherwise header not found
        return '';
    }

    /**
     * Extract the websocket request from out of the connection interface.
     *
     * @param ConnectionInterface $conn
     * @return RequestInterface
     * @throws \TypeError if somehow the websocket request doesn't exist
     */
    public static function extractWebSocketRequest(ConnectionInterface $conn): RequestInterface
    {
        // kosher according to http://socketo.me/docs/migration-3
        /**
         * @noinspection PhpUndefinedFieldInspection
         * @phpstan-ignore-next-line
         */
        return $conn->httpRequest;
    }

    /**
     * Prevent additional commands being executed by this pipe until it releases
     *
     * @param string $command
     * @return void
     */
    public function lock(string $command): void
    {
        $this->commandLock = $command;
    }

    /**
     * Is this pipe currently locked?
     *
     * @return bool
     */
    public function isLocked(): bool
    {
        return isset($this->commandLock);
    }

    /**
     * Release the command lock on this pipe
     *
     * @return void
     */
    public function unlock(): void
    {
        $this->commandLock = null;
    }
}
