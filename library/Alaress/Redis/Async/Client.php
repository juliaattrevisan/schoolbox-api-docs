<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Redis\Async;

use Alaress\Redis\Async\Event\Message;
use Alaress\Redis\Async\Event\PMessage;
use Predis;

/**
 * Redis asynchronous client wrapper: provides a more convenient interface for
 * subscribing to pub/sub queues.
 */
class Client
{
    /**
     * Constructor
     *
     * @param \Clue\React\Redis\Client $client The internal asynchronous Redis client: used only for subscriptions to
     *                                         pub/sub queues.
     */
    public function __construct(private \Clue\React\Redis\Client $client)
    {
    }

    /**
     * Subscribe to one or more channels.
     *
     * @param string|array<string> $channel  One or more channels to which to subscribe.
     * @param EventListener        $listener The event listener which handles messages on `$channel`.
     * @throws Predis\ClientException if the subscribe fails
     */
    public function subscribe(array|string $channel, EventListener $listener)
    {
        $channels = is_array($channel) ? $channel : [$channel];
        $this->client->subscribe($channels);
        $this->client->on('message', function (string $messageChannel, string $payload) use ($channels, $listener) {
            if (in_array($messageChannel, $channels)) {
                $listener->handle(new Message($payload, $messageChannel));
            }
        });
    }


    /**
     * Subscribe to one or more message patterns.
     *
     * @param string|array<string> $pattern  One or more patterns to which to subscribe.
     * @param EventListener        $listener The event listener which handles messages on channels matching `$pattern`.
     * @throws Predis\ClientException if the subscribe fails
     */
    public function psubscribe(string|array $pattern, EventListener $listener)
    {
        $patterns = is_array($pattern) ? $pattern : [$pattern];
        $this->client->psubscribe($patterns);
        $this->client->on('pmessage', function (string $messagePattern, string $messageChannel, string $payload) use ($patterns, $listener) {
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $messagePattern)) {
                    $event = new PMessage($payload, $messageChannel, $messagePattern);
                    $listener->handle($event);
                }
            }
        });
    }


    /**
     * Unsubscribe from one or more channels.
     *
     * @param string|array<string> $channel One or more channels from which to unsubscribe.
     * @throws Predis\ClientException if the unsubscribe fails
     */
    public function unsubscribe(array|string $channel)
    {
        $this->client->unsubscribe($channel);
    }


    /**
     * Unsubscribe from one or more message patterns.
     *
     * @param string|array<string> $pattern One or more patterns from which to unsubscribe.
     * @throws Predis\ClientException if the unsubscribe fails
     */
    public function punsubscribe(array|string $pattern)
    {
        $this->client->punsubscribe($pattern);
    }
}
