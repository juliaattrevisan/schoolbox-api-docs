<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Redis\Async;

use Alaress\Redis\Async\Event\IEvent;
use Alaress\Redis\Async\Event\Message;
use Alaress\Redis\Async\Event\PMessage;
use Alaress\Redis\Async\Event\Pong;
use React\EventLoop\LoopInterface;
use Predis;
use React\EventLoop\TimerInterface;
use stdClass;

class Client
{
//    /** @var Predis\Async\Client */
//    private $client;
//
//    private Predis\PubSub\Consumer $pubSubConsumer;
//
//    /**
//     * Subscriptions to a single channel.
//     *
//     * @var array<string, array<array{loop: Predis\PubSub\Consumer, listener: EventListener}>>
//     */
//    private array $subscriptions = [];
//
//    /**
//     * Subscriptions to a channel pattern.
//     *
//     * @var array<string, array<array{loop: Predis\PubSub\Consumer, listener: EventListener}>>
//     */
//    private array $patternSubscriptions = [];
//
//    /**
//     * The timer function used for fetching subscriptions.
//     */
//    private ?TimerInterface $timer = null;
//
//    /**
//     * @var array<EventListener>
//     */
//    private array $eventListeners = [];

    /**
     * @param Predis\ClientInterface $redisClient
     * @param string                 $dsn
     * @param LoopInterface          $loop
     */
    public function __construct(
        private Predis\ClientInterface $redisClient,
        $dsn,
        private LoopInterface $loop
    ) {
    }

    /**
     * @param string|string[] $channel  one or more channels to which to subscribe
     * @param EventListener   $listener
     * @throws Predis\ClientException if the subscribe fails
     */
    public function subscribe($channel, EventListener $listener)
    {
        $pubSubLoop = $this->redisClient->pubSubLoop();
        $pubSubLoop->subscribe($channel);
        $this->loop->addTimer(0.1, function () use ($pubSubLoop, $listener) {
            foreach ($pubSubLoop as $event) {
                if ($e = $this->castEvent($event)) {
                    $listener->handle($e);
                }
            }
        });
    }


    /**
     * @param string|string[] $pattern  one or more patterns to which to subscribe
     * @param EventListener   $listener
     * @throws Predis\ClientException if the subscribe fails
     */
    public function psubscribe($pattern, EventListener $listener)
    {
        $pubSubLoop = $this->redisClient->pubSubLoop();
        $pubSubLoop->psubscribe($pattern);
        $this->loop->addTimer(0.1, function () use ($pubSubLoop, $listener) {
            foreach ($pubSubLoop as $event) {
                if ($e = $this->castEvent($event)) {
                    $listener->handle($e);
                }
            }
        });
    }


    /**
     * @param string|string[] $channel  one or more channels from which to unsubscribe
     * @throws Predis\ClientException if the subscribe fails
     */
    public function unsubscribe($channel)
    {
        $pubSubLoop = $this->redisClient->pubSubLoop();
        $pubSubLoop->unsubscribe($channel);
    }


    /**
     * @param string|string[] $pattern  one or more patterns from which to unsubscribe
     * @throws Predis\ClientException if the subscribe fails
     */
    public function punsubscribe($pattern)
    {
        $pubSubLoop = $this->redisClient->pubSubLoop();
        $pubSubLoop->punsubscribe($pattern);
    }
//
//
//    private function addTimer(): TimerInterface
//    {
//        return $this->loop->addPeriodicTimer(
//            0.1,
//            function () {
//                foreach ($this->subscriptions as $subscriptions) {
//                    foreach ($subscriptions as $subscription) {
//                        foreach ($subscription['loop'] as $event) {
//                            if ($e = $this->castEvent($event)) {
//                                $subscription['listener']->handle($e);
//                            }
//                        }
//                    }
//                }
//                foreach ($this->patternSubscriptions as $subscriptions) {
//                    foreach ($subscriptions as $subscription) {
//                        foreach ($subscription['loop'] as $event) {
//                            if ($e = $this->castEvent($event)) {
//                                $subscription['listener']->handle($e);
//                            }
//                        }
//                    }
//                }
//            }
//        );
//    }

//    private function processSubscription(array $subscription)


//    /**
//     * @param string          $type
//     * @param string|string[] $arguments
//     * @param EventListener   $listener
//     * @throws Predis\ClientException if the subscribe fails
//     */
//    private function addSubscription($type, $arguments, EventListener $listener)
//    {
//        if (empty($arguments)) {
//            return;
//        }
//
//        $arguments = (array)$arguments;
//        $channels  = [ $type => $arguments ];
//
//        $this->client->pubSubLoop($channels, function (stdClass $event) use ($listener): void {
//            // cast this stdClass event object to a named class and push to the listener
//            if ($e = $this->castEvent($event)) {
//                $listener->handle($e);
//            }
//        });
//    }
//
//
//    /**
//     * @param string          $type
//     * @param string|string[] $arguments
//     * @throws Predis\ClientException if the unsubscribe fails
//     */
//    private function removeSubscription($type, $arguments)
//    {
//        if (empty($arguments)) {
//            return;
//        }
//
//        foreach ((array)$arguments as $argument) {
//            $this->client->$type($argument);
//        }
//    }


    /**
     * Cast a primitive object into an actual class
     *
     * @param stdClass $event
     * @return IEvent
     */
    private function castEvent(stdClass $event)
    {
        switch ($event->kind) {
            case 'pong':
                return new Pong($event->payload);
            case 'message':
                return new Message($event->payload, $event->channel);
            case 'pmessage':
                return new PMessage($event->payload, $event->channel, $event->pattern);
            default:
                // given its async nature, throwing an exception in here would be pointless
                return null;
        }
    }
}
