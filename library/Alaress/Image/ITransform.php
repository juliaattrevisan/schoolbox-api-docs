<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\Image;

use Imagine\Image\ImageInterface;

interface ITransform
{
    /**
     * Manipulate image.
     *
     * @param ImageInterface $image
     * @return ImageInterface The manipulated image
     */
    public function manipulate(ImageInterface $image): ImageInterface;
}
