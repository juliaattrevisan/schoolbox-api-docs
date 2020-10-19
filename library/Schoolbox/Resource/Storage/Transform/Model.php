<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Transform;

/**
 * Class Model
 * @package Schoolbox\Resource\Storage\Transform
 */
class Model
{
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
     * @var integer
     */
    private $size;

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
     * @param int $size
     *
     * @return $this
     */
    public function setSize(int $size): self
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
}
