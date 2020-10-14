<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\Image;

use Imagine\Image\ImageInterface;

/**
 * Implementations of this interface are able to apply an image manipulation
 */
interface ITransform
{
    /**
     * Manipulate image.
     *
     * Type of parameter and return depend upon implementation, it is however
     * expected that they will return the same type.
     *
     * @param  ImageInterface $image
     * @return ImageInterface The manipulated image
     */
    public function manipulate(ImageInterface $image): ImageInterface;
}
