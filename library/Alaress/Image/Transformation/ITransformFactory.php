<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Transformation;

use Alaress\Image\ITransform;

/**
 * Instances of this class are able to generate a specific transform given some implementation-specific parameters
 *
 * @method ITransform retrieve() Implementations will have implementation-specific param lists.
 */
interface ITransformFactory
{
    /**
     * Returns whether this class generated the specified cache key
     *
     * @param string $cacheKey
     * @return bool
     */
    public function recognisesCacheKey(string $cacheKey): bool;


    /**
     * Returns a Transform for the given cache key
     *
     * @param string $cacheKey
     * @return ITransform
     */
    public function retrieveForCacheKey(string $cacheKey): ITransform;

    /**
     * Returns a slug to identify this transform type
     *
     * @return string
     */
    public static function getSlug(): string;
}
