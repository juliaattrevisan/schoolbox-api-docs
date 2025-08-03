<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Redis\Async;

use Alaress\Redis\Async\Event\Message;
use Alaress\Redis\Async\Event\PMessage;
use React\EventLoop\LoopInterface;
use Predis;

class Client
{
    private \Clue\React\Redis\Client $client;

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
        $clientFactory = new \Clue\React\Redis\Factory($this->loop);
        $this->client  = $clientFactory->createLazyClient(
            sprintf('%s:%s', parse_url($dsn, PHP_URL_HOST), parse_url($dsn, PHP_URL_PORT)),
        );
    }

    /**
     * @param string|string[] $channel  one or more channels to which to subscribe
     * @param EventListener   $listener
     * @throws Predis\ClientException if the subscribe fails
     */
    public function subscribe($channel, EventListener $listener)
    {
        $this->client->subscribe($channel);
        $this->client->on('message', function (string $chan, string $payload) use ($channel, $listener) {
            if ($chan === $channel) {
                $listener->handle(new Message($payload, $chan));
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
        $this->client->psubscribe($pattern);
        $this->client->on('pmessage', function (string $patt, string $chan, string $payload) use ($pattern, $listener) {
            if (fnmatch($pattern, $patt)) {
                $event = new PMessage($payload, $chan, $patt);
                $listener->handle($event);
            }
        });
    }


    /**
     * @param string|string[] $channel  one or more channels from which to unsubscribe
     * @throws Predis\ClientException if the subscribe fails
     */
    public function unsubscribe($channel)
    {
        $this->client->unsubscribe($channel);
    }


    /**
     * @param string|string[] $pattern  one or more patterns from which to unsubscribe
     * @throws Predis\ClientException if the subscribe fails
     */
    public function punsubscribe($pattern)
    {
        $this->client->punsubscribe($pattern);
    }
}
