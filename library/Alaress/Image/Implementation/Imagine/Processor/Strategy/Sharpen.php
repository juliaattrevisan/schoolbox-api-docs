<?php
/**
 * Alaress_Image_Processor_Strategy_Sharpen
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Image\Implementation\Imagine\Processor\Strategy;

use Imagine\Image\ImageInterface;

class Sharpen extends BaseStrategy
{
    /**
     * {@inheritdoc}
     *
     * @param ImageInterface $image
     */
    public function manipulate($image)
    {
        $image->effects()->sharpen();
        return $image;
    }
}
