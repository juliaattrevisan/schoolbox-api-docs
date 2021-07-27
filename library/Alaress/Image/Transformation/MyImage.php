<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Transformation;

use Alaress\Image\ITransform;
use Alaress\Image\Transform\Constrain;

/**
 * Accepts dynamic url parameters to create a dynamic transformation list
 */
class MyImage implements ITransformFactory
{
    use HasTranscodePathTrait;

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
