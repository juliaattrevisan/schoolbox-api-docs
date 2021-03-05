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

use function in_array;

/**
 * A list of all the 'storage' transformations used throughout the site
 */
class Storage implements ITransformFactory
{
    public const TRANSFORM_SQUARE64 = 'square64';

    private const SIZES = [
        'square32',
        self::TRANSFORM_SQUARE64,
        'square128',
        'square200',
        'constrain200',
        'constrain300',
        'rectangle',
        'maxsize',
        'eportfolioImageGalleryLarge',
        'coverPhoto',
        'eportfolioCoverPhoto',
        'eportfolioRecentPhoto',
        'eportfolioImageGalleryThumbnail',
        'cardImageLarge',
        'cacheTest',
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
            case self::TRANSFORM_SQUARE64:
                return new Square(64);
            case 'square128':
                return new Square(128);
            case 'square200':
                return new Square(200);
            case 'constrain200':
                return new Constrain(200, 300);
            case 'constrain300':
                return new Constrain(300, 450);
            case 'rectangle':
                return new Rectangle(140, 50);
            case 'maxsize':
            case 'eportfolioImageGalleryLarge':
                // ePortfolio Image Gallery can probably be lightboxed
                return new Constrain(2048, 2048);
            case 'coverPhoto':
                // The cover photo on the News article page
                return new Constrain(2000, 465);
            case 'eportfolioCoverPhoto':
                // The huge cover photo on the ePortfolio Profile page
                return new Constrain(2000, 452);
            case 'eportfolioRecentPhoto':
                // ePortfolio Recent Photo list needs to be a square
                return new Rectangle(300, 300);
            case 'eportfolioImageGalleryThumbnail':
                // ePortfolio Image Gallery list needs to be a square
                return new Rectangle(100, 100);
            case 'cardImageLarge':
                // Card images are 2:1 ratio.  So this is used on class card
                return new Rectangle(800, 400);
            case 'cacheTest':
                // Test to find out whether Constrain skipped canvas creation
                // when resultant image dimension was the same as the original
                return new Constrain(247, 204);
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
        return "storage-$size";
    }


    /**
     * Returns whether this class generated the specified cache key
     *
     * @param string $cacheKey
     * @return bool
     */
    public function recognisesCacheKey(string $cacheKey): bool
    {
        if (!preg_match('/storage-([a-z\d]+)/', $cacheKey, $matches)) {
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
        preg_match('/storage-([a-z\d]+)/', $cacheKey, $matches);
        $size = $matches[1];
        return $this->retrieve($size);
    }


    /** @inheritDoc */
    public static function getSlug(): string
    {
        return 'storage';
    }
}
