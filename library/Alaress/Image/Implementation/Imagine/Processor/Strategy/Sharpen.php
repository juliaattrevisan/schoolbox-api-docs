<?php
/**
 * Alaress_Image_Processor_Strategy_Sharpen
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
