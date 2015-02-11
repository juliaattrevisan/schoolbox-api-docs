<?php
/**
 * Alaress_Image_Processor_Strategy_Original
 *
 * Returns original image in original sizing
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Implementation\Imagine\Processor\Strategy;

use Imagine\Image\ImageInterface;

class Original extends BaseStrategy
{
    /**
     * {@inheritdoc}
     *
     * @var ImageInterface $image
     */
    public function manipulate($image)
    {
        return $image;
    }
}
