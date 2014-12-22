<?php
/**
 * Sharpen
 *
 * Applies a cheap convolution matrix to sharpen images post-resize
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

namespace Alaress\Image\Implementation\Gd\Processor\Strategy;

class Sharpen extends BaseStrategy
{

    /**
    * Manipulate image
    *
    * @param $image
    * @return mixed
    */
    public function manipulate($image)
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
