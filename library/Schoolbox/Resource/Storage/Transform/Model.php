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
use Schoolbox\Video\ResourceVideo;

/**
 * This represents a transformation of a file.
 *
 * Normally the file is a storage file, but it may be a static file included in the codebase.
 * We currently support both 'image => image' and 'video => image' transforms.
 * In the case of images, the format is the same as the original image's format. For video's, it is always JPEG.
 */
class Model
{
    use UpdatedAtDate;

    public const STATE_PROCESSING = 'processing'; // initial state
    public const STATE_DONE       = 'done';       // successfully transformed, transform file saved to disk
    public const STATE_ERROR      = 'error';      // fatal error, do not try again
    public const STATE_FAILED     = 'failed';     // transform failed, transform file not on disk
                                                  // Reasons include: - original hash missing on disk
                                                  //                  - corrupt image

    public const STATES = [
        self::STATE_PROCESSING,
        self::STATE_DONE,
        self::STATE_ERROR,
        self::STATE_FAILED,
    ];

    /**
     * @var string
     */
    private string $hash;

    /**
     * The cacheKey does not always equal the transformation name.
     *
     * This is the latter fragment of the fileName, eg "[hash]-[cacheKey]"
     * The transformKey is just a name of some transformation, but that transformation may have
     * different parameters, and hence a single transform key can generate many cache keys.
     *
     * For video => image transforms, this will be just the name of the image size:
     * @see ResourceVideo::POSTER
     * @see ResourceVideo::THUMBNAIL
     *
     * @var string
     */
    private string $cacheKey;

    /**
     * Will be NULL if state is not yet 'done'
     *
     * @var null|int
     */
    private ?int $size;

    private ?string $state;

    /**
     * This is the format of the transformed file, it may be jpg, png, webp or gif
     * @var string
     */
    private string $format;

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

    /**
     * @param string $format
     *
     * @return $this
     */
    public function setFormat(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    /**
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }
}
