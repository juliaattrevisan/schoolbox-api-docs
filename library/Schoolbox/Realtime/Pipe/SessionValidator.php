<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;

use Database;
use Predis\ClientInterface;
use Predis\CommunicationException;
use Ratchet\Session\Serialize\HandlerInterface;
use Schoolbox\Realtime\SessionException;
use UnexpectedValueException;

class SessionValidator
{

    /** @var ClientInterface */
    protected $redis;

    /** @var HandlerInterface */
    protected $handler;

    /** @var int */
    protected $length;

    /** @var Database */
    protected $database;

    /**
     * User IDs, indexed by username
     *
     * @var int[]
     */
    protected $userIdCache = [];


    /**
     * @param ClientInterface  $redis
     * @param HandlerInterface $handler
     * @param int              $sessionLength
     * @param Database         $database
     */
    public function __construct(ClientInterface $redis, HandlerInterface $handler, $sessionLength, Database $database)
    {
        $this->redis    = $redis;
        $this->handler  = $handler;
        $this->length   = $sessionLength;
        $this->database = $database;
    }


    /**
     * Extracts a user from a Redis remember-me and returns the associated username
     *
     * @param string $rememberMe
     * @throws CommunicationException
     * @return int
     */
    public function findUserIdByRememberMe($rememberMe)
    {
        try {
            // redis will unfortunately respond with the username, not the id -_-
            $username = $this->findUser($rememberMe, 'remember-me token', '');

            if (empty($this->userIdCache[$username])) {
                // *sigh*
                $id = (int)$this->database
                    ->query('SELECT `id` FROM `user` WHERE `username` = ? LIMIT 0, 1', [ $username ])
                    ->fetchColumn()
                ;

                $this->userIdCache[$username] = $id;
            }

            return $this->userIdCache[$username];
        } catch (SessionException $e) {
            // this method must be safe to use
            return 0;
        }
    }


    /**
     * Extracts a user from a Redis session and returns the associated ID
     *
     * @param string $sessionId
     * @throws SessionException
     * @throws CommunicationException
     * @return int
     */
    public function findUserIdBySessionId($sessionId)
    {
        try {
            // note that objects DO NOT correctly unserialize
            $session = $this->handler->unserialize(
                $this->findUser($sessionId, 'sessionID', PipeManager::REDIS_SESSION_PREFIX)
            );
        } catch (UnexpectedValueException $e) {
            throw new SessionException('Could not decode session', 0, $e);
        }

        // check a user ID exists
        if (empty($session['id'])) {
            throw new SessionException('No valid user found in session');
        }

        // check the session hasn't gone stale
        if (empty($session['lasttime']) || time() - $session['lasttime'] > $this->length) {
            throw new SessionException('Session has expired');
        }

        return (int)$session['id'];
    }


    /**
     * @param string $token
     * @param string $label
     * @param string $prefix
     * @throws SessionException
     * @throws CommunicationException
     * @return string
     */
    protected function findUser($token, $label, $prefix)
    {
        if (!$token) {
            throw new SessionException("No $label provided on which to search.");
        }

        // this has the potential to throw CommunicationException
        $redisCommand  = $this->redis->createCommand('get', [ $prefix . $token ]);
        $redisResponse = $this->redis->executeCommand($redisCommand);

        if (!$redisResponse) {
            throw new SessionException("No session found for given $label.");
        }

        return $redisResponse;
    }

} 