<?php

/**
 * Staff
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\User;

use Schoolbox\External\Normalise\User\Common\Account;
use Schoolbox\External\Normalise\User\Common\Address;
use Schoolbox\External\Normalise\User\Common\House;
use Schoolbox\External\Normalise\User\Common\Phone;
use Schoolbox\External\Normalise\User\Staff\Meta;
use Schoolbox\External\Normalise\User\Staff\Year;
use Schoolbox\Collection;

class Staff extends Base
{

    /* Personal
     */

    /**
     * An instance of Year
     *
     * @var Staff\Year
     */
    protected $year = null;

    /**
     * Staff metadata
     *
     * @var Meta
     */
    private $meta = null;

    /* Contact
     */

    /**
     * Work phone
     *
     * @var Phone
     */
    protected $phoneWork = '';

    /**
     * House
     *
     * @var House
     */
    protected $house = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setup();
    }

    /**
     * Hook method
     */
    protected function setup()
    {
        $this->setAccount(new Account());
        $this->setPostalAddress(new Address());
        $this->setHomeAddress(new Address());
        $this->setEmail(new Collection());
        $this->setHomeEmail(new Collection());
        $this->setWorkEmail(new Collection());
        $this->setPhoneHome(new Phone());
        $this->setPhoneMobile(new Phone());
        $this->setHouse(new House(false));
        $this->setPhoneWork(new Phone());
        $this->setCampus(new Collection());
        $this->setMeta(new Meta());
        $this->setYear(new Year());
    }

    /**
     * Get a concatenated string representation of all the emails everywhere
     *
     * @param string|null $separator
     * @return string
     */
    public function getAllEmails($separator = "\n")
    {
        $output = array();

        foreach ($this->getEmail() as $currentEmail) {
            $output[] = $currentEmail->getAddress();
        }

        return implode($separator, $output);
    }

    /**
     * Get a concatenated string representation of all the campuse codes everywhere
     *
     * @param string|null $separator
     * @return string
     */
    public function getAllCampuses($separator = "\n")
    {
        $output = array();

        foreach ($this->getCampus() as $currentCampus) {
            $output[] = $currentCampus->getCode();
        }

        return implode($separator, $output);
    }

    /* Getters/Setters
     */

    /**
     * Set phone work
     *
     * @param Phone $phoneWork
     * @return $this
     */
    public function setPhoneWork($phoneWork)
    {
        $this->phoneWork = $phoneWork;
        return $this;
    }

    /**
     * Get phone work
     *
     * @return Phone
     */
    public function getPhoneWork()
    {
        return $this->phoneWork;
    }

    /**
     * Set campus
     *
     * @param \Schoolbox\Collection|Common\Campus[] $campus
     * @return $this
     */
    public function setCampus($campus)
    {
        $this->campus = $campus;
        return $this;
    }

    /**
     * Get campus
     *
     * @return \Schoolbox\Collection|Common\Campus[]
     */
    public function getCampus()
    {
        return $this->campus;
    }

    /**
     * Set meta
     *
     * @param Meta $meta
     * @return $this
     */
    public function setMeta($meta)
    {
        $this->meta = $meta;
        return $this;
    }

    /**
     * Get meta
     *
     * @return Meta
     */
    public function getMeta()
    {
        return $this->meta;
    }

    /**
     * Set year
     *
     * @param Year $year
     * @return $this
     */
    public function setYear($year)
    {
        $this->year = $year;
        return $this;
    }

    /**
     * Get year
     *
     * @return Year
     */
    public function getYear()
    {
        return $this->year;
    }
    /**
     * Set house
     *
     * @param House $house
     * @return $this
     */
    public function setHouse($house)
    {
        $this->house = $house;
        return $this;
    }

    /**
     * Get house
     *
     * @return House
     */
    public function getHouse()
    {
        return $this->house;
    }
}
