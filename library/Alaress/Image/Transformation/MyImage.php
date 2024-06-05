<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Transformation;

use Alaress\Image\ITransform;
use Alaress\Image\Transform\Constrain;
use Alaress\Image\Transform\Square;

/**
 * Accepts dynamic url parameters to create a dynamic transformation list
 */
class MyImage implements ITransformFactory
{
    use HasTranscodePathTrait;

    private const WIDTH_PIXELS_MIN = 32;
    private const WIDTH_PIXELS_MAX = 2048;
    private const WIDTH_PIXELS_DEFAULT = 200;
    private const HEIGHT_PIXELS_MIN = 32;
    private const HEIGHT_PIXELS_MAX = 2048;
    private const HEIGHT_PIXELS_DEFAULT = 200;

    /**
     * If supplied height and width are not within a reasonable range, use defaults.
     * @param mixed $width
     * @param mixed $height
     * @return array
     */
    public static function ensureDimensionsAreReasonable(mixed $width, mixed $height): array
    {
        if (
            $width === null || $width < self::WIDTH_PIXELS_MIN || $width > self::WIDTH_PIXELS_MAX ||
            $height === null || $height < self::HEIGHT_PIXELS_MIN || $height > self::HEIGHT_PIXELS_MAX
        ) {
            $width = self::WIDTH_PIXELS_DEFAULT;
            $height = self::HEIGHT_PIXELS_DEFAULT;
        }

        return [(int)$width, (int)$height];
    }

    /**
     * @param int $width
     * @param int $height
     * @return ITransform
     */
    public function retrieve(int $width, int $height): ITransform
    {
        return new Constrain($width, $height);
    }

    /**
     * @param int $size
     * @return ITransform
     */
    public function square(int $size): ITransform
    {
        return new Square($size);
    }

    /**
     * Returns a cache key for a transformation
     *
     * @param int $width
     * @param int $height
     * @return string
     */
    public function getCacheKey(int $width, int $height): string
    {
        return "{$width}x{$height}";
    }

    /**
     * Returns whether this class generated the specified cache key
     *
     * @param string $cacheKey
     * @return bool
     */
    public function recognisesCacheKey(string $cacheKey): bool
    {
        return preg_match('/\d+x\d+/', $cacheKey);
    }

    /**
     * Returns a Transform for the given cache key
     *
     * @param string $cacheKey
     * @return ITransform
     */
    public function retrieveForCacheKey(string $cacheKey): ITransform
    {
        [$width, $height] = explode('x', $cacheKey);
        return $this->retrieve((int)$width, (int)$height);
    }

    /** @inheritDoc */
    public static function getSlug(): string
    {
        return 'my_image';
    }
}
