<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Transform;

use Imagine\Exception as ImagineException;
use Alaress\Image\ITransform;
use Imagine\Image\ImageInterface;

/**
 * Constrain an image to fit within a specified width and height which is measured
 * from the center of the source image
 */
class Square implements ITransform
{
    /**
     * @var Rectangle
     */
    private $rectangleTransform;

    /**
     * @param int $size
     */
    public function __construct(int $size)
    {
        $this->rectangleTransform = new Rectangle($size, $size);
    }

    /**
     * @inheritDoc
     * @throws ImagineException\InvalidArgumentException
     * @throws ImagineException\OutOfBoundsException
     * @throws ImagineException\RuntimeException
     */
    public function manipulate(ImageInterface $image): ImageInterface
    {
        return $this->rectangleTransform->manipulate($image);
    }
}
