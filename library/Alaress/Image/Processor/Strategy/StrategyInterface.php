<?php
/**
 * Filetype factory for the original image transform implementation.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Processor\Strategy;

interface StrategyInterface
{
    /**
     * Manipulate image.
     *
     * Type of parameter and return depend upon implementation, it is however
     * expected that they will return the same type.
     *
     * @param  mixed $image
     * @return mixed The manipulated image
     */
    public function manipulate($image);
}
