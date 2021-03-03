<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Transform;

use InvalidArgumentException;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

/**
 * Class Model
 * @package Schoolbox\Resource\Storage\Transform
 */
class Model
{
    use UpdatedAtDate;

    public const STATE_PROCESSING = 'processing'; // initial state
    public const STATE_DONE       = 'done';       // successfully transformed, file saved to disk
    public const STATE_FAILED     = 'failed';     // transform failed, file not on disk

    public const STATES = [
        self::STATE_PROCESSING,
        self::STATE_DONE,
        self::STATE_FAILED,
    ];

    /**
     * @var string
     */
    private $hash;

    /**
     * The cacheKey does not always equal the transformation name.
     *
     * This is the latter fragment of the fileName, eg "[hash]-[cacheKey]"
     * The transformKey is just a name of some transformation, but that transformation may have
     * different parameters, and hence a single transform key can generate many cache keys.
     *
     * @var string
     */
    private $cacheKey;

    /**
     * Will be NULL if state is not yet 'done'
     *
     * @var null|int
     */
    private $size;

    private string $state;

    /**
     * @param string $hash
     *
     * @return Model
     */
    public function setHash(string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }

    /**
     * @param string $cacheKey
     *
     * @return $this
     */
    public function setCacheKey(string $cacheKey): self
    {
        $this->cacheKey = $cacheKey;

        return $this;
    }

    /**
     * @param null|int $size
     *
     * @return $this
     */
    public function setSize(?int $size): self
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @return string
     */
    public function getHash(): ?string
    {
        return $this->hash;
    }

    /**
     * @return string
     */
    public function getCacheKey(): ?string
    {
        return $this->cacheKey;
    }

    /**
     * @return int
     */
    public function getSize(): ?int
    {
        return $this->size;
    }


    /**
     * @return null|string
     */
    public function getState(): ?string
    {
        return $this->state;
    }


    /**
     * @param string $state
     * @return $this
     * @throws InvalidArgumentException
     */
    public function setState(string $state): self
    {
        if (!in_array($state, self::STATES)) {
            throw new InvalidArgumentException("Invalid state: '$state'");
        }
        $this->state = $state;
        return $this;
    }


    /**
     * @return bool
     * @throws InsufficientHydrationException
     */
    public function isStateDone(): bool
    {
        if ($this->state === null) {
            throw new InsufficientHydrationException("State is null");
        }
        return $this->state === self::STATE_DONE;
    }
}
