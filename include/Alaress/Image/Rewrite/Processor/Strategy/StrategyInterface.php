<?php
/**
 * StrategyInterface
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Rewrite\Processor\Strategy;

use Intervention\Image\Image;

interface StrategyInterface
{
    /**
     * Manipulate image
     *
     * @param  Image $image
     * @return Image The manipulated image
     */
    public function manipulate(Image $image);

    /**
     * @param  bool  $supports_alpha_channel
     * @return $this
     */
    public function setSupportsAlphaChannel($supports_alpha_channel);

    /**
     * @return bool
     */
    public function getSupportsAlphaChannel();
}
