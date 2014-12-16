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

namespace Alaress\Image\Rewrite\Intervention\Processor\Strategy;

use Intervention\Image\Image;

class Original extends BaseStrategy
{
    /**
     * {@inheritdoc}
     *
     * @var Image $image
     */
    public function manipulate($image)
    {
        return $image;
    }
}
