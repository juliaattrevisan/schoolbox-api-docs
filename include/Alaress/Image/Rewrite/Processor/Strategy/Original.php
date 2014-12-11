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

namespace Alaress\Image\Rewrite\Processor\Strategy;

class Original extends BaseStrategy implements StrategyInterface
{

    /**
    * Manipulate image
    *
    * @param $image
    * @return mixed
    */
    public function manipulate($image)
    {
        return $image;
    }
}
