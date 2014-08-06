<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;


use Predis\ClientInterface;
use Predis\CommunicationException;
use Ratchet\Session\Serialize\HandlerInterface;
use Schoolbox\Realtime\SessionException;
use UnexpectedValueException;

class SessionValidator {

    /** @var ClientInterface */
    protected $redis;

    /** @var HandlerInterface */
    protected $handler;

    /** @var int */
    protected $length;


    /**
     * @param ClientInterface $redis
     * @param HandlerInterface $handler
     * @param int $sessionLength
     */
    public function __construct(ClientInterface $redis, HandlerInterface $handler, $sessionLength) {
        $this->redis = $redis;
        $this->handler = $handler;
        $this->length = $sessionLength;
    }

    /**
     * Extracts a user by ID from a Redis session and returns the associated User object
     *
     * @param string $sessionId
     * @throws SessionException
     * @throws CommunicationException
     * @return int
     */
    public function findUserIdBySessionId($sessionId) {
        if(!$sessionId) {
            throw new SessionException("No sessionID provided on which to search.");
        }

        $redisResponse = $this->checkRedis($sessionId, 2);

        if (!$redisResponse) {
            throw new SessionException("No session found for given session key.");
        }

        try {
            // note that objects DO NOT correctly unserialize
            $session = $this->handler->unserialize($redisResponse);

            // check a user ID exists
            if (empty($session['id'])) {
                throw new SessionException("No valid user found in session");
            }

            // check the session hasn't gone stale
            if (empty($session['lasttime']) || time() - $session['lasttime'] > $this->length) {
                throw new SessionException("Session has expired");
            }

            return (int)$session['id'];

        } catch(UnexpectedValueException $e) {
            throw new SessionException("Could not decode session", 0, $e);
        }
    }


    /**
     * Recursively attempt to solicit a response from Redis, which may have gone to sleep
     *
     * @param string $sessionId
     * @param int $retries
     * @return mixed
     * @throws CommunicationException
     */
    protected function checkRedis($sessionId, $retries = 0) {
        try {
            $redisCommand  = $this->redis->createCommand('get', array(PipeManager::REDIS_SESSION_PREFIX . $sessionId));
            return $this->redis->executeCommand($redisCommand);
        }
        catch(CommunicationException $e) {
            // redis timed out, this attempt should have reawoken it

            if($retries <= 0) {
                // if we've run out of retries, rethrow the exception
                throw $e;
            }

            // recurse
            return $this->checkRedis($sessionId, $retries - 1);
        }

    }
} 