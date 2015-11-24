<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Rules\Token;


class Filter 
{

    /** @var string */
    protected $filter;

    /** @var string */
    protected $blurb;

    /** @var callable */
    protected $effect;


    /**
     * @param string   $filter
     * @param string   $description
     * @param callable $effect
     */
    public function __construct($filter, $description, callable $effect)
    {
        $this->filter = $filter;
        $this->blurb  = $description;
        $this->effect = $effect;
    }


    /**
     * @return string
     */
    public function getFilter()
    {
        return $this->filter;
    }


    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->blurb;
    }


    /**
     * @param string $input
     * @return string
     */
    public function apply($input)
    {
        return call_user_func($this->effect, $input);
    }
} 