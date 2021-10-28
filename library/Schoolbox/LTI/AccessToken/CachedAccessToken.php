<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\AccessToken;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Entity\AccessToken;
use Psr\Cache\CacheItemInterface;

/**
 * Access token cache item: a wrapper around an AccessToken object.
 */
class CachedAccessToken implements CacheItemInterface
{
    /**
     * Expire an access token after 5 minutes by default.
     */
    public const DEFAULT_EXPIRATION = 300;

    /**
     * The model this cache item wraps.
     *
     * TODO make this wrap a `OAT\Library\Lti1p3Core\Security\OAuth2\Entity\AccessToken` instead
     */
    private AccessToken $token;

    /**
     * Constructor
     *
     * @param AccessToken $token
     */
    public function __construct(AccessToken $token)
    {
        $this->token = $token;
    }

    /**
     * The model's identifier is the cache key.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->token->getIdentifier();
    }

    /**
     * Returns the model the cache item wraps.
     *
     * @return AccessToken
     */
    public function get()
    {
        return $this->token;
    }

    /**
     * If this model has an identifier, it's a hit; otherwise it's a miss.
     *
     * TODO the identifier could exist even if the item isn't persisted
     *
     * @return bool
     */
    public function isHit()
    {
        return $this->token->getIdentifier() !== null;
    }

    /**
     * Set the model this cache item wraps.
     *
     * @param AccessToken $value
     * @return static
     */
    public function set($value)
    {
        $this->token = $value;
        return $this;
    }

    /**
     * Set the expiry of this cache item to `$expiration`:
     * * if a `DateTimeInterface`: to that time
     * * otherwise: to `self::DEFAULT_EXPIRATION` seconds from now
     *
     * @param DateTimeInterface|null $expiration
     * @return static
     */
    public function expiresAt($expiration)
    {
        if ($expiration instanceof DateTimeInterface) {
            $this->token->setExpiryDateTime(new DateTimeImmutable($expiration->getTimestamp()));
        } else {
            $this->token->setExpiryDateTime(
                (new DateTimeImmutable())
                    ->add(new DateInterval(sprintf('PT%dS', self::DEFAULT_EXPIRATION)))
            );
        }
        return $this;
    }

    /**
     * Increment the expiry of this cache item by `$time`:
     * * if a `DateInterval`: the increment in that interval
     * * if an int: to that number of seconds from now
     * * otherwise: to `self::DEFAULT_EXPIRATION` seconds from now
     *
     * @param DateInterval|int|null $time
     * @return static
     */
    public function expiresAfter($time)
    {
        if ($time instanceof DateInterval) {
            $interval = $time;
        } elseif (is_int($time)) {
            $interval = new DateInterval(sprintf('PT%dS', $time));
        } else {
            $interval = new DateInterval(sprintf('PT%dS', self::DEFAULT_EXPIRATION));
        }
        $this->token->setExpiryDateTime($this->token->getExpiryDateTime()->add($interval));
        return $this;
    }
}
