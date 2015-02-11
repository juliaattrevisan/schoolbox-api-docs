<?php
/**
 * Alaress_Image_Processor_Strategy_Sharpen
 *
 * Applies a cheap convolution matrix to sharpen images post-resize
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Implementation\Imagine\Processor\Strategy;

use Imagine\Image\Effects\ConvolutionMatrix;
use Imagine\Image\ImageInterface;

class Sharpen extends BaseStrategy
{
    /**
     * {@inheritdoc}
     *
     * @var ImageInterface $image
     */
    public function manipulate($image)
    {
        $image->effects()->convolve(
            new ConvolutionMatrix(
                -0.2,
                -1,
                -0.2,
                -1,
                15,
                -1,
                -0.2,
                -1,
                -0.2
            )
        );
        return $image;
    }
}
