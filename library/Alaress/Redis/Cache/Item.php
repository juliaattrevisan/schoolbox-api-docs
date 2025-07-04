<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Redis\Cache;

use DateInterval;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

/**
 * Generic PSR-6 cache item, currently only used by the Redis cache.
 */
class Item implements CacheItemInterface
{
    /**
     * Constructor
     *
     * @param string                $key
     * @param bool                  $isHit
     * @param mixed                 $value
     * @param DateInterval|int|null $ttl
     */
    public function __construct(
        private string $key,
        private bool $isHit,
        private mixed $value,
        private DateInterval|int|null $ttl = null,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @inheritdoc
     */
    public function get(): mixed
    {
        return $this->value;
    }

    /**
     * @inheritdoc
     */
    public function isHit(): bool
    {
        return $this->isHit;
    }

    /**
     * @inheritdoc
     */
    public function set(mixed $value): static
    {
        $new        = clone $this;
        $new->value = $value;
        return $new;
    }

    /**
     * @inheritdoc
     */
    public function expiresAt(?DateTimeInterface $expiration): static
    {
        // Convert to TTL
        $new      = clone $this;
        $new->ttl = $expiration ? $expiration->getTimestamp() - time() : null;
        return $new;
    }

    /**
     * @inheritdoc
     */
    public function expiresAfter(DateInterval|int|null $time): static
    {
        $new      = clone $this;
        $new->ttl = $time;
        return $new;
    }

    /**
     * What is the TTL of this item?
     *
     * @return int|null Number of seconds to live, or null if no TTL.
     */
    public function getTtl(): ?int
    {
        return $this->ttl instanceof DateInterval ? $this->ttl->s : null;
    }
}
