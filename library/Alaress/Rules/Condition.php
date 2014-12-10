<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Rules;


abstract class Condition
{

    /** @var bool */
    protected $negate;

    /** @var callable */
    protected $test;


    /**
     * @param callable $test
     * @param bool     $negate
     */
    public function __construct($test, $negate = false)
    {
        $this->test = $test;
        $this->negate = $negate;
    }


    /**
     * @param mixed $input
     * @return bool
     */
    public function test($input)
    {
        try {
            return $this->negate ^ call_user_func($this->test, $input);
        } catch (\DomainException $e) {
            return false;
        }
    }


    /**
     * @return bool
     */
    public function isNegated()
    {
        return $this->negate;
    }


    /**
     * @return string
     */
    public abstract function getSummary();

}