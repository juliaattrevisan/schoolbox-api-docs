<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\Image\Transform;

use Alaress\Image\ITransform;
use Imagine\Exception as ImagineException;
use Imagine\Image\ImageInterface;

/**
 * Images from iphones/ipads and some cameras have rotation information in the exif data
 * This transformation makes sure that the image is rotated to the correct orientation
 * before any other transformations take place.
 */
class RotateOrientation implements ITransform
{
    /**
     * @inheritDoc
     * @throws ImagineException\RuntimeException
     */
    public function manipulate(ImageInterface $image): ImageInterface
    {
        $metadata = $image->metadata();
        switch ($metadata['ifd0.Orientation'] ?? null) {
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
            case 1:
            default:
                break;
        }
        $profile = $image->palette()->profile();
        $image->strip();
        $image->profile($profile);
        return $image;
    }
}
