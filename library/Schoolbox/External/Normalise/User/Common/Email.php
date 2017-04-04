<?php
/**
 * Email
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Normalise\User\Common;

class Email {

    /**
     * Email address
     *
     * @var string
     */
    private $address = '';

    /**
     * Constructor
     */
    public function __construct() {
    }

    /* Getters/Setters
     */

    /**
     * Set address
     *
     * @param string $address
     */
    public function setAddress($address)
    {
        $this->address = $address;
    }

    /**
     * Get address
     *
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }
}