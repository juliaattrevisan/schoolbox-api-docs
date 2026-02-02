<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Test;

class ObjectReaderStringableClass
{
    /** @var string */
    protected $string;


    /**
     * @param string $string
     */
    public function __construct($string)
    {
        $this->string = $string;
    }


    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->string;
    }
}
