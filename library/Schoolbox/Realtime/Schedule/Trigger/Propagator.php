<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger;

use Alaress\Async\Broker\IBroker;
use JsonSerializable;
use Predis\ClientInterface;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Schedule\Event\CrudEventInterface;
use Schoolbox\Realtime\Schedule\Event\Event;
use Schoolbox\Realtime\Schedule\Event\Splitter;
use Schoolbox\User;

/**
 * Accepts the parameters of a CRUD action and broadcasts the resultant triggers into the async abyss
 *
 * @package Schoolbox\Realtime\Schedule\Trigger
 */
class Propagator implements IPropagator
{
    /** @var Splitter */
    private $splitter;

    /** @var ClientInterface */
    private $redis;

    /** @var IBroker */
    private $broker;

    /** @var string */
    private $instanceId;


    /**
     * @param Splitter        $splitter
     * @param ClientInterface $redis
     * @param IBroker         $broker
     * @param string          $instanceId
     */
    public function __construct(Splitter $splitter, ClientInterface $redis, IBroker $broker, $instanceId)
    {
        $this->splitter   = $splitter;
        $this->redis      = $redis;
        $this->broker     = $broker;
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
     * @return string
     */
    public function propagate(User $subject, $verb, $object, $mapper, array $diff = null)
    {
        // this event generation *should* be in a factory, but... ¯\_(ツ)_/¯
        return $this->propagateEvent(new Event($subject, $verb, $object, $mapper, $diff));
    }


    /**
     * Take a CRUD event and propagate triggers. Returns the key used to store the event in Redis.
     *
     * @param CrudEventInterface $event
     * @return null
     */
    public function propagateEvent(CrudEventInterface $event)
    {
        $namespace = "msg:{$this->instanceId}:";

        // drop event serial into redis (commented out because this will eat all the memory)
        //$redisKey = $this->toRedis($event, "{$namespace}e");

        foreach ($this->splitter->generateTriggers($event) as $trigger) {
            $this->broker->publish($trigger, [ $namespace ]);
        }

        return null;
    }


    /**
     * @param object $object
     * @param string $prefix
     * @return string
     */
    private function toRedis($object, $prefix)
    {
        if ($object instanceof JsonSerializable) {
            // we *prefer* JSON if possible
            $serial = json_encode($object);
        } else {
            $serial = serialize($object);
        }

        $hash   = sha1($serial);
        $key    = trim("$prefix:$hash", ':');

        $this->redis->set($key, $serial);

        return $key;
    }

}
