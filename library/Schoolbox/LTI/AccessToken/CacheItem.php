<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\AccessToken;

use Alaress_Date_Format;
use DateInterval;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

/**
 * Access token cache item: basically, a wrapper around a model.
 */
class CacheItem implements CacheItemInterface
{
    /**
     * Expire an access token after 5 minutes by default.
     */
    public const DEFAULT_EXPIRATION = 300;

    /**
     * The model this cache item wraps.
     */
    private Model $model;

    /**
     * Constructor
     *
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * The model's identifier is the cache key.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->model->getIdentifier();
    }

    /**
     * Returns the model the cache item wraps.
     *
     * @return Model
     */
    public function get()
    {
        return $this->model;
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
        return $this->model->getIdentifier() !== null;
    }

    /**
     * Set the model this cache item wraps.
     *
     * @param Model $value
     * @return static
     */
    public function set($value)
    {
        $this->model = $value;
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
            $this->model->setExpiresAt(new Alaress_Date_Format($expiration));
        } else {
            $this->model->setExpiresAt((Alaress_Date_Format::now())->addSeconds(self::DEFAULT_EXPIRATION));
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
        $expiresAt = Alaress_Date_Format::now();
        if ($time instanceof DateInterval) {
            $invert    = $time->invert ? -1 : 1;
            $expiresAt = $expiresAt
                ->addYears($invert * $time->y)
                ->addMonths($invert * $time->m)
                ->addDays($invert * $time->d)
                ->addHours($invert * $time->h)
                ->addMinutes($invert * $time->m)
                ->addSeconds($invert * $time->s);
        } elseif (is_int($time)) {
            $expiresAt = $expiresAt->addSeconds($time);
        } else {
            $expiresAt = $expiresAt->addSeconds(self::DEFAULT_EXPIRATION);
        }
        $this->model->setExpiresAt($expiresAt);
        return $this;
    }
}
