<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Processor;

use Alaress\Image\ITransform;

/**
 * Strongly-typed representation of a desired image transformation
 */
class Transformation
{
    private string $hash;

    private string $cacheKey;

    private ITransform $transform;

    public function __construct(string $hash, string $cacheKey, ITransform $transform)
    {
        $this->hash      = $hash;
        $this->cacheKey  = $cacheKey;
        $this->transform = $transform;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getCacheKey(): string
    {
        return $this->cacheKey;
    }

    public function getTransform(): ITransform
    {
        return $this->transform;
    }
}
