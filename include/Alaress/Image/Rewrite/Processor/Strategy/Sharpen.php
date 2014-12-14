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

use Imagine\Image\ImageInterface;

class Sharpen extends BaseStrategy implements StrategyInterface
{
    /**
     * {@inheritdoc}
     */
    public function manipulate(ImageInterface $image)
    {
        $image->effects()->sharpen();
        return $image;
    }
}
