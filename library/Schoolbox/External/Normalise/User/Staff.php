<?php
/**
 * Staff
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User;
use Schoolbox\External\Normalise\User\Common\Account;
use Schoolbox\External\Normalise\User\Common\Address;
use Schoolbox\External\Normalise\User\Common\Phone;
use Schoolbox\External\Normalise\User\Staff\Meta;
use Schoolbox\Collection;

class Staff extends Base {

    /* Personal
     */

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
     * Constructor
     */
    public function __construct() {
        $this->setup();
    }

    /**
     * Hook method
     */
    protected function setup() {
        $this->setAccount(new Account());
        $this->setPostalAddress(new Address());
        $this->setHomeAddress(new Address());
        $this->setEmail(new Collection());
        $this->setWorkEmail(new Collection());
        $this->setPhoneHome(new Phone());
        $this->setPhoneMobile(new Phone());
        $this->setPhoneWork(new Phone());
        $this->setCampus(new Collection());
        $this->setMeta(new Meta());
    }

    /**
     * Get a concatenated string representation of all the emails everywhere
     *
     * @param $separator
     * @return string
     */
    public function getAllEmails($separator = "\n") {
        $output = array();

        foreach($this->getEmail() as $currentEmail) {
            $output[] = $currentEmail->getAddress();
        }

        return implode($separator, $output);
    }

    /**
     * Get a concatenated string representation of all the campuse codes everywhere
     *
     * @param $separator
     * @return string
     */
    public function getAllCampuses($separator = "\n") {
        $output = array();

        foreach($this->getCampus() as $currentCampus) {
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
}
