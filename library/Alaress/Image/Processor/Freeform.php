<?php
/**
 * Alaress_Image_Processor_Freeform
 * This allows you to perform certain transformations dynamically, passing
 * arguments into the transformation defintion from the querystring.
 * This is accomplished via the Transformation class, demonstrated in MyImage.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Processor;

class Freeform extends Standard
{

    /**
     * The transform key to use, defaults to "Constrain".
     *
     * @var string
     */
    protected $transform_key = 'Constrain';

    /**
     * @param  string $transform_key
     *
     * @return $this
     */
    public function setTransformKey($transform_key)
    {
        $this->transform_key = $transform_key;
        return $this;
    }

    /**
     * Overwrite parent method of getting transform key from the cache
     *
     * @return string
     */
    protected function getTransformKey()
    {
        return $this->transform_key;
    }
}
