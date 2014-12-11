<?php
/**
 * Phone
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User\Common;


class Phone {

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
     *
     * Immutable
     *
     * @param $number
     * @param $silent
     */
    public function __construct($number, $silent) {
        $this->setNumber($number);
        $this->setSilent($silent);
    }

    /* Getters/Setters
     */

    /**
     * Set number
     *
     * @param string $number
     */
    private function setNumber($number)
    {
        $this->number = $number;
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
     */
    private function setSilent($silent)
    {
        $this->silent = $silent;
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