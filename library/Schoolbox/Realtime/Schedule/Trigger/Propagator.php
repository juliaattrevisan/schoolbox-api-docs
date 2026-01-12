<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger;

use Alaress\Async\Broker\IBroker;
use Alaress\Async\Notification\Instance\Instance;
use ReflectionException;
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

    /** @var IBroker */
    private $broker;

    /** @var string */
    private $instanceId;


    /**
     * @param Splitter        $splitter
     * @param IBroker         $broker
     * @param string          $instanceId
     */
    public function __construct(Splitter $splitter, IBroker $broker, $instanceId)
    {
        $this->splitter   = $splitter;
        $this->broker     = $broker;
        $this->instanceId = $instanceId;
    }


    /**
     * Take CRUD parameters and propagate triggers. Returns the key used to store the event in Redis.
     *
     * @param User       $subject
     * @param string     $verb
     * @param object     $object
     * @param Mapper     $mapper
     * @param array|null $diff
     * @return string
     */
    public function propagate(User $subject, $verb, $object, $mapper, ?array $diff = null)
    {
echo "Schedule\Trigger\Propagator->propagate() e \$event->getStartDateTime()->getDatabase(): " . $object->getStartDateTime()?->getDatabase() . "\n";
        // this event generation *should* be in a factory, but... ¯\_(ツ)_/¯
        $newEvent = new Event($subject, $verb, $object, $mapper, $diff);
echo "Schedule\Trigger\Propagator->propagate() 1 \$event->getStartDateTime()->getDatabase(): " . $object->getStartDateTime()?->getDatabase() . "\n";
        $returnValue = $this->propagateEvent($newEvent);
echo "Schedule\Trigger\Propagator->propagate() x \$event->getStartDateTime()->getDatabase(): " . $object->getStartDateTime()?->getDatabase() . "\n";
        return $returnValue;
    }


    /**
     * Take a CRUD event and propagate triggers. Returns the key used to store the event in Redis.
     *
     * @param CrudEventInterface $event
     * @return string|null
     * @throws ReflectionException
     */
    public function propagateEvent(CrudEventInterface $event)
    {
echo "Schedule\Trigger\Propagator->propagateEvent() e \$event->getStartDateTime()->getDatabase(): " . $event->getObject()->getStartDateTime()?->getDatabase() . "\n";
        $namespace = "msg:{$this->instanceId}:";

        // drop event serial into redis (commented out because this will eat all the memory)
        //$redisKey = $this->toRedis($event, "{$namespace}e");
        foreach ($this->splitter->generateTriggers($event) as $trigger) {
echo "Schedule\Trigger\Propagator->propagateEvent() 1 \$event->getStartDateTime()->getDatabase(): " . $event->getObject()->getStartDateTime()?->getDatabase() . "\n";
            $diff = \Alaress_Date_Format::now()->diffInSeconds(new \Alaress_Date_Format($trigger->getTimestamp(), 'U'));
echo "Schedule\Trigger\Propagator->propagateEvent() 2 \$event->getStartDateTime()->getDatabase(): " . $event->getObject()->getStartDateTime()?->getDatabase() . "\n";
            $ttl = ($diff < 0) ? Instance::DEFAULT_TTL : ($diff + Instance::DEFAULT_TTL);
echo "Schedule\Trigger\Propagator->propagateEvent() 3 \$event->getStartDateTime()->getDatabase(): " . $event->getObject()->getStartDateTime()?->getDatabase() . "\n";
            $this->broker->publish($trigger, [$namespace], $ttl);
        }
echo "Schedule\Trigger\Propagator->propagateEvent() x \$event->getStartDateTime()->getDatabase(): " . $event->getObject()->getStartDateTime()?->getDatabase() . "\n";

        return null;
    }
}
