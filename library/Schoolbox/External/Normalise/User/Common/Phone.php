<?php

/**
 * Phone
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\User\Common;

class Phone
{

    /**
     * The phone number
     *
     * @var string
     */
    private $number = '';

    /**
     * This phone is a silent number
     *
     * @var bool
     */
    private $silent = false;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /* Getters/Setters
     */

    /**
     * Set number
     *
     * @param string $number
     * @return $this
     */
    public function setNumber($number)
    {
        $this->number = $number;
        return $this;
    }

    /**
     * Get number
     *
     * @return string
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Set silent
     *
     * @param boolean $silent
     * @return $this
     */
    public function setSilent($silent)
    {
        $this->silent = $silent;
        return $this;
    }

    /**
     * Get silent
     *
     * @return boolean
     */
    public function getSilent()
    {
        return $this->silent;
    }
}
