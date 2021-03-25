<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Transformation;

use Alaress\Image\ITransform;
use Alaress\Image\Transform\Constrain;
use Alaress\Image\Transform\Rectangle;
use Alaress\Image\Transform\Square;
use OutOfBoundsException;

/**
 * A list of all the 'portrait' transformations used throughout the site
 */
class Portrait implements ITransformFactory
{
    private const SIZES = [
        'square32',
        'square48',
        'square64',
        'square72',
        'square80',
        'square200',
        'constrain64',
        'constrain128',
        'constrain200',
        'profile',
        'rectangle',
    ];

    /**
     * @param string $size
     * @return ITransform
     * @throws OutOfBoundsException
     */
    public function retrieve(string $size): ITransform
    {
        switch ($size) {
            case 'square32':
                return new Square(32);
            case 'square48':
                return new Square(48);
            case 'square64':
                return new Square(64);
            case 'square72':
                return new Square(72);
            case 'square80':
                return new Square(80);
            case 'square200':
                return new Square(200);
            case 'constrain64':
                return new Constrain(64, 96);
            case 'constrain128':
                return new Constrain(128, 400);
            case 'constrain200':
                return new Constrain(200, 300);
            case 'profile':
                return new Constrain(250, 300);
            case 'rectangle':
                return new Rectangle(140, 50);
        }
        throw new OutOfBoundsException('Could not load transformation list.');
    }


    /**
     * Returns a cache key for a transformation
     *
     * @param string $size
     * @return string
     */
    public function getCacheKey(string $size): string
    {
        return "portrait-$size";
    }


    /**
     * Returns whether this class generated the specified cache key
     *
     * @param string $cacheKey
     * @return bool
     */
    public function recognisesCacheKey(string $cacheKey): bool
    {
        if (!preg_match('/portrait-([a-z\d]+)/', $cacheKey, $matches)) {
            return false;
        }
        return in_array($matches[1], self::SIZES, true);
    }


    /**
     * Returns a Transform for the given cache key
     *
     * @param string $cacheKey
     * @return ITransform
     * @throws OutOfBoundsException
     */
    public function retrieveForCacheKey(string $cacheKey): ITransform
    {
        preg_match('/portrait-([a-z\d]+)/', $cacheKey, $matches);
        $size = $matches[1];
        return $this->retrieve($size);
    }


    /** @inheritDoc */
    public static function getSlug(): string
    {
        return 'portrait';
    }
}
