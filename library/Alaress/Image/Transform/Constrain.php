<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\Image\Transform;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Exception as ImagineException;

/**
 * Constrain an image to fit within a specified width and height
 *
 * The entire original image will be resized with aspect preserved, to fill the maximum amount
 * of space possible without having any of the original image cropped out.
 */
class Constrain extends HasDimensions
{
    /**
     * @inheritDoc
     * @throws ImagineException\InvalidArgumentException
     * @throws ImagineException\RuntimeException
     */
    public function manipulate(ImageInterface $image): ImageInterface
    {
        return $image->thumbnail(
            new Box(
                $this->getCanvasWidth(),
                $this->getCanvasHeight()
            )
        );
    }
}
