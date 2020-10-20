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
 * A list of all the transformations used throughout the site
 */
class Storage
{
    public const TRANSFORM_SQUARE64 = 'square64';
    /**
     * @param string $key
     * @return ITransform
     * @throws OutOfBoundsException
     */
    public function retrieve(string $key): ITransform
    {
        switch ($key) {
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
                return new Rectangle(80, 80);
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
}
