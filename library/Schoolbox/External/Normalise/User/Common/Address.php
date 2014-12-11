<?php
/**
 * Address
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User\Common;


class Address {

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
     *
     * Immutable
     * 
     * @param $address1
     * @param $address2
     * @param $address3
     * @param $suburb
     * @param $state
     * @param $postcode
     * @param $country
     */
    public function __construct($address1, $address2, $address3, $suburb, $state, $postcode, $country) {
        $this->setAddress1($address1);
        $this->setAddress2($address2);
        $this->setAddress3($address3);
        $this->setSuburb($suburb);
        $this->setState($state);
        $this->setPostcode($postcode);
        $this->setCountry($country);
    }

    /**
     * Set address 1
     *
     * @param string $address1
     */
    private function setAddress1($address1)
    {
        $this->address1 = $address1;
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
     */
    private function setAddress2($address2)
    {
        $this->address2 = $address2;
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
     */
    private function setAddress3($address3)
    {
        $this->address3 = $address3;
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
     */
    private function setCountry($country)
    {
        $this->country = $country;
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
     */
    private function setPostcode($postcode)
    {
        $this->postcode = $postcode;
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
     */
    private function setState($state)
    {
        $this->state = $state;
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
     */
    private function setSuburb($suburb)
    {
        $this->suburb = $suburb;
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