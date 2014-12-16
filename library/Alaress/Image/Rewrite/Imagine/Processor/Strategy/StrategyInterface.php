<?php
/**
 * StrategyInterface
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Rewrite\Imagine\Processor\Strategy;

use Imagine\Image\ImageInterface;

interface StrategyInterface
{
    /**
     * Manipulate image
     *
     * @param  ImageInterface $image
     * @return ImageInterface The manipulated image
     */
    public function manipulate(ImageInterface $image);
}
