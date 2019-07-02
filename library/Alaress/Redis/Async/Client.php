<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Redis\Async;

use Alaress\Redis\Async\Event\IEvent;
use Alaress\Redis\Async\Event\Message;
use Alaress\Redis\Async\Event\PMessage;
use Alaress\Redis\Async\Event\Pong;
use Clue\React\Redis;
use React\EventLoop\LoopInterface;
use Predis;
use React\Promise\PromiseInterface;
use stdClass;

class Client
{
    private const SUBSCRIBE    = 'subscribe';
    private const PSUBSCRIBE   = 'psubscribe';
    private const MESSAGE      = 'message';
    private const PMESSAGE     = 'pmessage';
    private const UNSUBSCRIBE  = 'unsubscribe';
    private const PUNSUBSCRIBE = 'punsubscribe';


    /**
     * @var Redis\Client
     */
    private $client;


    /**
     * @var PromiseInterface
     */
    private $clientPromise;

    /**
     * @param string        $dsn
     * @param LoopInterface $loop
     */
    public function __construct($dsn, LoopInterface $loop)
    {
        $clientFactory       = new Redis\Factory($loop);
        $this->clientPromise = $clientFactory->createClient($dsn);
    }

    /**
     * @param string|string[] $channel  one or more channels to which to subscribe
     * @param EventListener   $listener
     * @throws Predis\ClientException if the subscribe fails
     */
    public function subscribe($channel, EventListener $listener)
    {
        if (empty($channel)) {
            return;
        }

        $channels = (array)$channel;
        $this->clientPromise->then(
            function (Redis\Client $client) use ($channels, $listener) {
                foreach ($channels as $subChannel) {
                    $client->subscribe($subChannel);
                    $client->on(self::MESSAGE, function ($pubChannel, $payload) use ($subChannel, $listener) {
                        if ($pubChannel === $subChannel) {
                            $listener->handle(new Message(json_decode($payload), $pubChannel));
                        }
                    });
                }
            }
        );
    }


    /**
     * @param string|string[] $pattern  one or more patterns to which to subscribe
     * @param EventListener   $listener
     * @throws Predis\ClientException if the subscribe fails
     */
    public function psubscribe($pattern, EventListener $listener)
    {
        if (empty($pattern)) {
            return;
        }

        $patterns = (array)$pattern;
        $this->clientPromise->then(
            function (Redis\Client $client) use ($patterns, $listener) {
                foreach ($patterns as $subPattern) {
                    $client->psubscribe($subPattern);
                    $client->on(self::PMESSAGE, function ($pubPattern, $channel, $payload) use ($subPattern, $listener) {
                        if ($pubPattern === $subPattern) {
                            $listener->handle(new PMessage(json_decode($payload), $channel, $pubPattern));
                        }
                    });
                }
            }
        );
    }


    /**
     * @param string|string[] $channel  one or more channels from which to unsubscribe
     * @throws Predis\ClientException if the subscribe fails
     */
    public function unsubscribe($channel)
    {
        $this->removeSubscription(self::UNSUBSCRIBE, $channel);
    }


    /**
     * @param string|string[] $pattern  one or more patterns from which to unsubscribe
     * @throws Predis\ClientException if the subscribe fails
     */
    public function punsubscribe($pattern)
    {
        $this->removeSubscription(self::PUNSUBSCRIBE, $pattern);
    }


//    /**
//     * @param string          $type
//     * @param string          $event
//     * @param string|string[] $arguments
//     * @param EventListener   $listener
//     * @throws Predis\ClientException if the subscribe fails
//     */
//    private function addSubscription(string $type, string $event, $arguments, EventListener $listener)
//    {
//        if (empty($arguments)) {
//            return;
//        }
//
//        $arguments = (array)$arguments;
//        $channels  = [ $type => $arguments ];
//
//        $this->clientPromise->then(
//            function (Redis\Client $client) use ($type, $event, $arguments, $listener) {
//                foreach ($arguments as $argument) {
//                    // $type will be subscribe or psubscribe
//                    $client->$type($argument);
//
//                    // $event will be message or pmessage
//                    $client->on($event, function () {
//
//                    });
//                }
//            }
//        );
//
//        $this->client->pubSubLoop($channels, function (stdClass $event) use ($listener) {
//            // cast this stdClass event object to a named class and push to the listener
//            if ($e = $this->castEvent($event)) {
//                $listener->handle($e);
//            }
//        });
//    }


    /**
     * @param string          $type
     * @param string|string[] $arguments
     * @throws Predis\ClientException if the unsubscribe fails
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


//    /**
//     * Cast a primitive object into an actual class
//     *
//     * @param stdClass $event
//     * @return IEvent
//     */
//    private function castEvent(stdClass $event)
//    {
//        switch ($event->kind) {
//            case Predis\Async\PubSub\Consumer::PONG:
//                return new Pong($event->payload);
//            case Predis\Async\PubSub\Consumer::MESSAGE:
//                return new Message($event->payload, $event->channel);
//            case Predis\Async\PubSub\Consumer::PMESSAGE:
//                return new PMessage($event->payload, $event->channel, $event->pattern);
//            default:
//                // given its async nature, throwing an exception in here would be pointless
//                return null;
//        }
//    }
}
