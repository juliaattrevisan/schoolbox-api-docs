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
 * This class encapsulates a Socket (pipe) and its associated information
 *
 * Class Pipe
 * @package Schoolbox\Realtime
 */
class Pipe implements PipeInterface
{

    /**
     * @var string
     */
    private $commandLock;


    /**
     * @var ConnectionInterface
     */
    protected $socketConnection;


    /**
     * @var int
     */
    protected $userId;


    /**
     * @var string
     */
    protected $cookies;


    /**
     * @param ConnectionInterface $conn
     */
    public function __construct(ConnectionInterface $conn)
    {
        $this->socketConnection = $conn;
    }


    /**
     * @param ConnectionInterface $conn
     * @return string
     */
    public static function getSocketKeyForConnection(ConnectionInterface $conn)
    {
        return static::getHeader($conn, 'sec-websocket-key');
    }


    /**
     * Returns the Sec-Websocket-Key header of this websocket (unique connection identifier)
     * @return string
     */
    public function getSocketKey()
    {
        return self::getSocketKeyForConnection($this->socketConnection);
    }


    /**
     * Returns the given cookie of this websocket, if present.
     *
     * @param string $name
     * @return string
     */
    public function getCookie($name)
    {
        // only do this once
        if (!$this->cookies) {
            $cookieString = static::getHeader($this->socketConnection, 'cookie');

            preg_match_all('`([A-Za-z]+)=([^;]+)`', $cookieString, $matches);
            $this->cookies = array_combine($matches[1], $matches[2]);
        }

        return isset($this->cookies[$name]) ? $this->cookies[$name] : '';
    }


    /**
     * Returns the session id of this websocket, if it has one.
     *
     * @return string
     */
    public function getSession()
    {
        return $this->getCookie(PipeManager::PHP_SESSION_PREFIX);
    }


    /**
     * Returns the remote address of the connection of this pipe.
     *
     * @return string
     */
    public function getRemoteAddress()
    {
        // kosher according to http://socketo.me/docs/migration-3
        return $this->socketConnection->remoteAddress ?: '0.0.0.0';
    }


    /**
     * @return void
     */
    public function close()
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
     * @param string $data
     * @return $this
     */
    public function send($data)
    {
        $this->socketConnection->send($data);
        return $this;
    }


    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }


    /**
     * @param int $id
     */
    public function setUserId($id)
    {
        $this->userId = $id;
    }


    /**
     * @param ConnectionInterface $conn
     * @param string $header
     * @return string
     */
    protected static function getHeader(ConnectionInterface $conn, $header)
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
    protected static function extractWebSocketRequest(ConnectionInterface $conn): RequestInterface
    {
        // kosher according to http://socketo.me/docs/migration-3
        return $conn->httpRequest;
    }


    /**
     * Prevent additional commands being executed by this pipe until it releases
     *
     * @param string $command
     * @return void
     */
    public function lock($command)
    {
        $this->commandLock = $command;
    }


    /**
     * Is this pipe currently locked?
     *
     * @return bool
     */
    public function isLocked()
    {
        return isset($this->commandLock);
    }


    /**
     * Release the command lock on this pipe
     *
     * @return void
     */
    public function unlock()
    {
        $this->commandLock = null;
    }
}
