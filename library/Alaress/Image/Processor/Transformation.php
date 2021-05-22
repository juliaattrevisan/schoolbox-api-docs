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

    /**
     * Original file extension. Null if unknown
     *
     * @var string|null
     */
    private ?string $ext;

    /**
     * Original file size. Null if unknown
     *
     * @var int|null
     */
    private ?int $fileSize;

    public function __construct(string $hash, string $cacheKey, ITransform $transform, ?string $ext, ?int $fileSize)
    {
        $this->hash      = $hash;
        $this->cacheKey  = $cacheKey;
        $this->transform = $transform;
        $this->ext       = $ext;
        $this->fileSize  = $fileSize;
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

    public function getExt(): ?string
    {
        return $this->ext;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }
}
