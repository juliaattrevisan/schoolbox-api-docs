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
use Schoolbox\External\Normalise\User\Staff\Type;
use Schoolbox\Collection;

class Staff extends Base {

    /* Personal
     */

    /**
     * Associated campus
     *
     * @var null
     */
    private $campus = null;

    /**
     * Staff type
     *
     * @var Type
     */
    private $type = null;

    /* Contact
     */

    /**
     * Phone extension
     *
     * @var string
     */
    private $extension = '';

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
        $this->setPhoneHome(new Phone());
        $this->setPhoneMobile(new Phone());
        $this->setPhoneWork(new Phone());
        $this->setCampus(new Collection());
        $this->setType(new Type());
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
     * @param null $campus
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
     * @return null
     */
    public function getCampus()
    {
        return $this->campus;
    }

    /**
     * Set extension
     *
     * @param string $extension
     * @return $this
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;
        return $this;
    }

    /**
     * Get extension
     *
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * Set type
     *
     * @param Type $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get type
     *
     * @return Type
     */
    public function getType()
    {
        return $this->type;
    }
}