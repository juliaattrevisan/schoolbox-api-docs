<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\CacheFactory;

use Predis\ClientInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * Factory for Redis-backed caches.
 */
class Factory
{
    public function __construct(private ClientInterface $redis)
    {
    }

    public function build(string $namespace): CacheInterface
    {
        return new SentryWrapper(
            new Psr16Cache(new RedisAdapter($this->redis, $namespace . '.' . INSTANCE_ID)),
            $namespace,
        );
    }
}
