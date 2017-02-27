<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger;

use Alaress\Async\AMQP\AMQPInterface;
use Aura\Di\Exception\ReflectionFailure;
use Predis\ClientInterface;
use Redis;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Schedule\Event\Event;
use Schoolbox\Realtime\Schedule\Event\Splitter;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\User;

/**
 * Accepts the parameters of a CRUD action and broadcasts the resultant triggers into the async abyss
 *
 * @package Schoolbox\Realtime\Schedule\Trigger
 */
class Propagator
{
    const AMQP_TOPIC = 'trigger.sb';

    /** @var Splitter */
    private $splitter;

    /** @var Redis|ClientInterface */
    private $redis;

    /** @var AMQPInterface */
    private $amqp;

    /** @var string */
    private $instanceId;


    /**
     * @param Splitter        $splitter
     * @param ClientInterface $redis
     * @param AMQPInterface   $amqp
     * @param string          $instanceId
     */
    public function __construct(Splitter $splitter, ClientInterface $redis, AMQPInterface $amqp, $instanceId)
    {
        $this->splitter   = $splitter;
        $this->redis      = $redis;
        $this->amqp       = $amqp;
        $this->instanceId = $instanceId;
    }


    /**
     * Take CRUD parameters and propagate triggers. Returns the key used to store the event in Redis.
     *
     * @param User   $subject
     * @param string $verb
     * @param object $object
     * @param Mapper $mapper
     * @param array  $diff
     * @throws ReflectionFailure
     * @throws ScheduleException
     * @return string
     */
    function propagate(User $subject, $verb, $object, $mapper, array $diff = null)
    {
        $event = new Event($subject, $verb, $object, $mapper, $diff);

        // drop event serial into redis
        $redisKey = $this->toRedis($event, "e:{$this->instanceId}");
        $triggers = $this->splitter->generateTriggers($event);

        foreach ($triggers as $trigger) {
            // drop trigger serial into redis
            $triggerKey = $this->toRedis($trigger, "t:{$this->instanceId}");

            // push trigger key onto rabbit
            $this->toAmqp($triggerKey, self::AMQP_TOPIC);
        }

        return $redisKey;
    }


    /**
     * @param object $object
     * @param string $prefix
     * @return string
     */
    private function toRedis($object, $prefix)
    {
        $serial = serialize($object);
        $hash   = sha1($serial);
        $key    = trim("$prefix:$hash", ':');

        $this->redis->set($key, $serial);

        return $key;
    }


    /**
     * @param string $payload
     * @param string $topic
     */
    private function toAmqp($payload, $topic)
    {
        $this->amqp->publish($payload, $topic);
    }

}
