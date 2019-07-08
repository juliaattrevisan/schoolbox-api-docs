<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Transformation;


interface Transformation
{

    /**
     * @param string $key
     * @return array
     */
    public function retrieve($key);
} 
