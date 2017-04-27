<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;

use Guzzle\Http\Message\EntityEnclosingRequest;
use Guzzle\Http\Message\Header;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\Version\RFC6455;


/**
 * This class encapsulates a Socket (pipe) and its associated information
 *
 * Class Pipe
 * @package Schoolbox\Realtime
 */
class Pipe implements PipeInterface
{
    /**
	 * @param ConnectionInterface $conn
	 * @return string
	 */
	public static function getSocketKeyForConnection(ConnectionInterface $conn)
    {
        if ($conn instanceof RFC6455\Connection) {
            return static::getHeader($conn, 'sec-websocket-key');
        }
        // don't attempt to handle non-RFC6455 sockets
        return '';
	}


    /** @var string */
    private $commandLock;

    /** @var \Ratchet\ConnectionInterface */
	protected $socketConnection;

	/** @var int */
	protected $userId;

	/** @var string */
	protected $cookies;

	/**
	 * @param RFC6455\Connection $conn
	 */
	public function __construct(RFC6455\Connection $conn) {
		$this->socketConnection = $conn;
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
	public function getRemoteAddress() {
		return $this->socketConnection->remoteAddress ?: '0.0.0.0';
	}

    /**
     * @return void
     */
	public function close() {
		$this->socketConnection->close();
	}

	/**
	 * @param string $data
	 * @return $this
	 */
	public function send($data) {
		$this->socketConnection->send($data);
		return $this;
	}


	/**
	 * @return int
	 */
	public function getUserId() {
		return $this->userId;
	}

	/**
	 * @param int $id
	 */
	public function setUserId($id) {
		$this->userId = $id;
	}

    /**
     * @param RFC6455\Connection $conn
     * @param string $header
     * @return string
     */
	protected static function getHeader(RFC6455\Connection $conn, $header)
    {
		$rq = self::extractWebSocketRequest($conn);

        $value = $rq->getHeader($header);

        if ($value instanceof Header) {
            $value = $value->toArray();
			return (string)reset($value);
		}
		// otherwise header not found
		return '';
	}

    /**
     * @param RFC6455\Connection $conn
     * @return EntityEnclosingRequest
     */
	protected static function extractWebSocketRequest(RFC6455\Connection $conn)
    {
        return $conn->WebSocket->request;
    }

    /**
     * Prevent additional commands being executed by this pipe until it releases
     *
     * @param $command
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