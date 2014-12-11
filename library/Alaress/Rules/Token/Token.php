<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Rules\Token;


class Token {

    /** @var string */
    protected $token;
    /** @var string */
    protected $description;
    /** @var callable */
    protected $callback;


    /**
     * @param string   $token
     * @param string   $description
     * @param callable $callback
     */
    public function __construct($token, $description, $callback)
    {
        $this->token       = $token;
        $this->description = $description;
        $this->callback    = $callback;
    }


    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }


    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }


    /**
     * @param mixed $input
     * @param array $settings
     * @return string
     */
    public function apply($input, $settings)
    {
        return call_user_func($this->callback, $input, $settings);
    }

}