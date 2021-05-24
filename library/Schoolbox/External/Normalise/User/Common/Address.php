<?php

/**
 * Address
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\User\Common;

class Address
{

    /**
     * Address 1
     *
     * @var string
     */
    private $address1 = '';

    /**
     * Address 2
     *
     * @var string
     */
    private $address2 = '';

    /**
     * Address 3
     *
     * @var string
     */
    private $address3 = '';

    /**
     * Suburb
     *
     * @var string
     */
    private $suburb = '';

    /**
     * State
     *
     * @var string
     */
    private $state = '';

    /**
     * Postcode
     *
     * @var string
     */
    private $postcode = '';

    /**
     * Country
     *
     * @var string
     */
    private $country = '';

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Get entire address as a string
     *
     * @param string|null $separator
     * @return string
     */
    public function getAsString($separator = "\n")
    {
        $address = array_filter(array(
            $this->getAddress1(),
            $this->getAddress2(),
            $this->getAddress3(),
            "{$this->getSuburb()} {$this->getState()} {$this->getPostCode()}",
            $this->getCountry()
        ));

        return implode($separator, $address);
    }

    /* Getters/Setters
     */

    /**
     * Set address 1
     *
     * @param string $address1
     * @return $this
     */
    public function setAddress1($address1)
    {
        $this->address1 = $address1;
        return $this;
    }

    /**
     * Get address 1
     *
     * @return string
     */
    public function getAddress1()
    {
        return $this->address1;
    }

    /**
     * Set address 2
     *
     * @param string $address2
     * @return $this
     */
    public function setAddress2($address2)
    {
        $this->address2 = $address2;
        return $this;
    }

    /**
     * Get address 2
     *
     * @return string
     */
    public function getAddress2()
    {
        return $this->address2;
    }

    /**
     * Set address 3
     *
     * @param string $address3
     * @return $this
     */
    public function setAddress3($address3)
    {
        $this->address3 = $address3;
        return $this;
    }

    /**
     * Get address 3
     *
     * @return string
     */
    public function getAddress3()
    {
        return $this->address3;
    }

    /**
     * Set country
     *
     * @param string $country
     * @return $this
     */
    public function setCountry($country)
    {
        $this->country = $country;
        return $this;
    }

    /**
     * Get country
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * Set postcode
     *
     * @param string $postcode
     * @return $this
     */
    public function setPostcode($postcode)
    {
        $this->postcode = $postcode;
        return $this;
    }

    /**
     * Get postcode
     *
     * @return string
     */
    public function getPostcode()
    {
        return $this->postcode;
    }

    /**
     * Set state
     *
     * @param string $state
     * @return $this
     */
    public function setState($state)
    {
        $this->state = $state;
        return $this;
    }

    /**
     * Get state
     *
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Set suburb
     *
     * @param string $suburb
     * @return $this
     */
    public function setSuburb($suburb)
    {
        $this->suburb = $suburb;
        return $this;
    }

    /**
     * Get suburb
     *
     * @return string
     */
    public function getSuburb()
    {
        return $this->suburb;
    }
}
