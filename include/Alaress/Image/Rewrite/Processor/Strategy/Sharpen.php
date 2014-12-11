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

namespace Alaress\Image\Rewrite\Processor\Strategy;

use Intervention\Image\Image;

class Sharpen extends BaseStrategy implements StrategyInterface
{
    /**
     * {@inheritdoc}
     */
    public function manipulate(Image $image)
    {
        $sharpenMatrix = array (
            array(-0.2, -1, -0.2),
            array(-1, 15, -1),
            array(-0.2, -1, -0.2)
        );

        $divisor = array_sum(array_map('array_sum', $sharpenMatrix));

        $offset = 0;

        imageconvolution($image, $sharpenMatrix, $divisor, $offset);

        return $image;
    }
}
