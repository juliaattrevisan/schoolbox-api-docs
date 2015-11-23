<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Test;


class ObjectReaderStringableClass
{
    /** @var string */
    protected $string;


    /**
     * @param string $string
     */
    function __construct($string)
    {
        $this->string = $string;
    }


    /**
     * @return string
     */
    function __toString()
    {
        return (string)$this->string;
    }

}