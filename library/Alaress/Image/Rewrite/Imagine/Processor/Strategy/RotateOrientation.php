<?php
/**
 * Alaress_Image_Processor_Strategy_RotateOrientation
 *
 * Images from iphones/ipads and some cameras have rotation information in the exif data
 * This transformation makes sure that the image is rotated to the correct orientation
 * before any other transformations take place.
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Image\Rewrite\Imagine\Processor\Strategy;

use Imagine\Filter\Basic\Autorotate;
use Imagine\Image\ImageInterface;

class RotateOrientation extends BaseStrategy
{
    /**
     * {@inheritdoc}
     *
     * @var ImageInterface $image
     */
    public function manipulate($image)
    {
        $metadata = $image->metadata();
        switch (isset($metadata['ifd0.Orientation']) ? $metadata['ifd0.Orientation'] : null) {
            case 1:
                break;
            case 2:
                $image->flipHorizontally();
                break;
            case 3:
                $image->rotate(180);
                break;
            case 4:
                $image->rotate(180)->flipHorizontally();
                break;
            case 5:
                $image->rotate(90)->flipHorizontally();
                break;
            case 6:
                $image->rotate(90);
                break;
            case 7:
                $image->rotate(-90)->flipHorizontally();
                break;
            case 8:
                $image->rotate(-90);
                break;
            default:
                break;
        }
        return $image;
    }
}
