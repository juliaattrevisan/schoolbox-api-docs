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
     * Stored message listeners created by `subscribe`, keyed by channel.
     *
     * @var array<string, callable>
     */
    private array $messageListeners = [];

    /**
     * Stored message listeners created by `psubscribe`, keyed by pattern.
     *
     * @var array<string, callable>
     */
    private array $pmessageListeners = [];

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
        $promises = [];
        foreach ($channels as $ch) {
            $handler = function (string $messageChannel, string $payload) use ($ch, $listener) {
                if ($messageChannel === $ch) {
                    $listener->handle(new Message($payload, $messageChannel));
                }
            };
            $this->client->on('message', $handler);
            $this->messageListeners[$ch] = $handler;
            /** @phpstan-ignore-next-line method.notFound */
            $promises[] = $this->client->subscribe($ch);
        }
        return Promise\all($promises);
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
        $promises = [];
        foreach ($patterns as $p) {
            $handler = function (string $messagePattern, string $messageChannel, string $payload) use ($p, $listener) {
                if ($messagePattern === $p) {
                    $listener->handle(new PMessage($payload, $messageChannel, $messagePattern));
                }
            };
            $this->client->on('pmessage', $handler);
            $this->pmessageListeners[$p] = $handler;
            /** @phpstan-ignore-next-line method.notFound */
            $promises[] = $this->client->psubscribe($p);
        }
        return Promise\all($promises);
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
        foreach ($channels as $ch) {
            if (isset($this->messageListeners[$ch])) {
                $this->client->removeListener('message', $this->messageListeners[$ch]);
                unset($this->messageListeners[$ch]);
            }
        }
        /** @phpstan-ignore-next-line method.notFound */
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
        foreach ($patterns as $p) {
            if (isset($this->pmessageListeners[$p])) {
                $this->client->removeListener('pmessage', $this->pmessageListeners[$p]);
                unset($this->pmessageListeners[$p]);
            }
        }
        /** @phpstan-ignore-next-line method.notFound */
        return Promise\all(array_map($this->client->punsubscribe(...), $patterns));
    }
}
