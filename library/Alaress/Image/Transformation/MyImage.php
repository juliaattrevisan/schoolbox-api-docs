<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Transformation;

use Alaress\Image\ITransform;
use Alaress\Image\Transform\Constrain;

/**
 * Accepts dynamic url parameters to create a dynamic transformation list
 */
class MyImage
{
    /**
     * @param int $width
     * @param int $height
     * @return ITransform
     */
    public function retrieve(int $width, int $height): ITransform
    {
        return new Constrain($width, $height);
    }
}
