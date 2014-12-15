<?php
/**
 * Guardian
 *
 * Because I can't call it Parent
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
use Schoolbox\Collection;

class Guardian extends Base {

    /* Contact
     */

    /**
     * Work phone
     *
     * @var Phone
     */
    protected $phoneWork = '';

    /**
     * Guardian type e.g. SC1, SC2
     *
     * @var string
     */
    protected $type = '';

    /**
     * Guardian role e.g. mother, father
     *
     * @var string
     */
    protected $role = '';

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
        $this->setPhoneHome(new Phone());
        $this->setPhoneMobile(new Phone());
        $this->setPhoneWork(new Phone());
        $this->setCampus(new Collection());
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
     * Set type
     *
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set role
     *
     * @param string $role
     */
    public function setRole($role)
    {
        $this->role = $role;
    }

    /**
     * Get role
     *
     * @return string
     */
    public function getRole()
    {
        return $this->role;
    }
}