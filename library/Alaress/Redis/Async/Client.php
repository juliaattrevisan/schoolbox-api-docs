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
use Clue\React\Redis\Client as ReactRedisClient;
use React\Promise;
use React\Promise\PromiseInterface;

/**
 * Redis asynchronous client wrapper: provides a more convenient interface for
 * subscribing to pub/sub queues.
 */
class Client
{
    /**
     * Constructor
     *
     * @param ReactRedisClient $client The internal asynchronous Redis client.
     *                                 This instance of the client may only be used for subscriptions to pub/sub queues:
     *                                 once a client has started subscribing to a queue, it cannot be used for other
     *                                 commands as per https://github.com/clue/reactphp-redis?tab=readme-ov-file#pubsub.
     */
    public function __construct(private ReactRedisClient $client)
    {
    }

    /**
     * Subscribe to one or more channels.
     *
     * @param string|array<string> $channel  One or more channels to which to subscribe.
     * @param EventListener        $listener The event listener which handles messages on `$channel`.
     * @return PromiseInterface Resolves successfully if all subscriptions are successful.
     */
    public function subscribe(array|string $channel, EventListener $listener): PromiseInterface
    {
        $channels = is_array($channel) ? $channel : [$channel];

        /** @phpstan-ignore-next-line method.notFound - subscribe does exist */
        $promise = Promise\all(array_map($this->client->subscribe(...), $channels));
        $this->client->on('message', function (string $messageChannel, string $payload) use ($channels, $listener) {
            if (in_array($messageChannel, $channels)) {
                $listener->handle(new Message($payload, $messageChannel));
            }
        });
        return $promise;
    }


    /**
     * Subscribe to one or more message patterns.
     *
     * @param string|array<string> $pattern  One or more patterns to which to subscribe.
     * @param EventListener        $listener The event listener which handles messages on channels matching `$pattern`.
     * @return PromiseInterface Resolves successfully if all subscriptions are successful.
     */
    public function psubscribe(string|array $pattern, EventListener $listener): PromiseInterface
    {
        $patterns = is_array($pattern) ? $pattern : [$pattern];

        /** @phpstan-ignore-next-line method.notFound - psubscribe does exist */
        $promise = Promise\all(array_map($this->client->psubscribe(...), $patterns));
        $this->client->on('pmessage', function (string $messagePattern, string $messageChannel, string $payload) use ($patterns, $listener) {
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $messagePattern)) {
                    $event = new PMessage($payload, $messageChannel, $messagePattern);
                    $listener->handle($event);
                }
            }
        });
        return $promise;
    }


    /**
     * Unsubscribe from one or more channels.
     *
     * @param string|array<string> $channel One or more channels from which to unsubscribe.
     * @return PromiseInterface Resolves successfully if all unsubscriptions are successful.
     */
    public function unsubscribe(array|string $channel): PromiseInterface
    {
        $channels = is_array($channel) ? $channel : [$channel];
        /** @phpstan-ignore-next-line method.notFound - unsubscribe does exist */
        return Promise\all(array_map($this->client->unsubscribe(...), $channels));
    }


    /**
     * Unsubscribe from one or more message patterns.
     *
     * @param string|array<string> $pattern One or more patterns from which to unsubscribe.
     * @return PromiseInterface Resolves successfully if all unsubscriptions are successful.
     */
    public function punsubscribe(array|string $pattern): PromiseInterface
    {
        $patterns = is_array($pattern) ? $pattern : [$pattern];
        /** @phpstan-ignore-next-line method.notFound - punsubscribe does exist */
        return Promise\all(array_map($this->client->punsubscribe(...), $patterns));
    }
}
