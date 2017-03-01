<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Redis\Async;

use Alaress\Redis\Async\Event\Event;
use Alaress\Redis\Async\Event\Message;
use Alaress\Redis\Async\Event\PMessage;
use Alaress\Redis\Async\Event\Pong;
use InvalidArgumentException;
use React\EventLoop\LoopInterface;
use Predis;
use stdClass;

class Client
{

    /** @var Predis\Async\Client */
    private $client;

    /**
     * @param string        $dsn
     * @param LoopInterface $loop
     */
    public function __construct($dsn, LoopInterface $loop)
    {
        $this->client = new Predis\Async\Client($dsn, $loop);
    }

    /**
     * @param string|string[] $channel  one or more channels to which to subscribe
     * @param EventListener   $listener
     */
    public function subscribe($channel, EventListener $listener)
    {
        $this->addSubscription(Predis\Async\PubSub\Consumer::SUBSCRIBE, $channel, $listener);
    }


    /**
     * @param string|string[] $pattern  one or more patterns to which to subscribe
     * @param EventListener   $listener
     */
    public function psubscribe($pattern, EventListener $listener)
    {
        $this->addSubscription(Predis\Async\PubSub\Consumer::PSUBSCRIBE, $pattern, $listener);
    }


    /**
     * @param string|string[] $channel  one or more channels from which to unsubscribe
     */
    public function unsubscribe($channel)
    {
        $this->removeSubscription(Predis\Async\PubSub\Consumer::UNSUBSCRIBE, $channel);
    }


    /**
     * @param string|string[] $pattern  one or more patterns from which to unsubscribe
     */
    public function punsubscribe($pattern)
    {
        $this->removeSubscription(Predis\Async\PubSub\Consumer::PUNSUBSCRIBE, $pattern);
    }


    /**
     * @param string          $type
     * @param string|string[] $arguments
     * @param EventListener   $listener
     */
    private function addSubscription($type, $arguments, EventListener $listener)
    {
        if (empty($arguments)) {
            return;
        }

        $arguments = (array)$arguments;
        $channels  = [ $type => $arguments ];

        $this->client->pubSubLoop($channels, function (stdClass $event) use ($listener) {
            // cast this stdClass event object to a named class and push to the listener
            $listener->handle($this->castEvent($event));
        });
    }


    /**
     * @param string          $type
     * @param string|string[] $arguments
     */
    private function removeSubscription($type, $arguments)
    {
        if (empty($arguments)) {
            return;
        }

        foreach ((array)$arguments as $argument) {
            $this->client->$type($argument);
        }
    }


    /**
     * Cast a primitive object into an actual class
     *
     * @param stdClass $event
     * @throws InvalidArgumentException
     * @return Event
     */
    private function castEvent(stdClass $event)
    {
        switch ($event->kind) {
            case Predis\Async\PubSub\Consumer::PONG:
                return new Pong($event->payload);
            case Predis\Async\PubSub\Consumer::MESSAGE:
                return new Message($event->payload, $event->channel);
            case Predis\Async\PubSub\Consumer::PMESSAGE:
                return new PMessage($event->payload, $event->channel, $event->pattern);
            default:
                throw new InvalidArgumentException("Unknown event type '{$event->kind}'.");
        }
    }
}