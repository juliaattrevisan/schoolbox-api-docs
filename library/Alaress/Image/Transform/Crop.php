<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Transform;

use Alaress\Image\ITransform;
use Imagine\Exception as ImagineException;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;

/**
 * Crops out a rectangle from an image. The boundaries of the cropping is defined in percentages rather than
 * pixels, as this will cater for multiple images of various dimensions needing to be cropped in the same way.
 *
 * x0,y0 are the top-left coordinates of an image expressed as percentages
 * x1,y1 are the bottom-right coordinates of an image expressed as percentages
 *
 * e.g. (5,10) and (66,75) will crop from 5%,10% to 66%,75%
 *
 * The percentage conversion is done internally, so you can just put in values between 0 and 100
 */
class Crop implements ITransform
{
    /** @var float */
    private $x0;
    /** @var float */
    private $y0;
    /** @var float */
    private $x1;
    /** @var float */
    private $y1;

    /**
     * @param int $x0
     * @param int $y0
     * @param int $x1
     * @param int $y1
     */
    public function __construct(int $x0, int $y0, int $x1, int $y1)
    {
        $this->x0 = $x0 * 0.01;
        $this->y0 = $y0 * 0.01;
        $this->x1 = $x1 * 0.01;
        $this->y1 = $y1 * 0.01;
    }

    /**
     * @inheritDoc
     * @throws ImagineException\InvalidArgumentException
     * @throws ImagineException\OutOfBoundsException
     * @throws ImagineException\RuntimeException
     */
    public function manipulate(ImageInterface $image): ImageInterface
    {
        // Figure out resized dimensions
        $currentWidth  = $image->getSize()->getWidth();
        $currentHeight = $image->getSize()->getHeight();

        // Width of area to crop
        $src_w = $currentWidth * ($this->x1 - $this->x0);
        $src_h = $currentHeight * ($this->y1 - $this->y0);

        $src_x = $currentWidth * $this->x0;
        $src_y = $currentHeight * $this->y0;

        return $image->crop(new Point($src_x, $src_y), new Box($src_w, $src_h));
    }
}
