<?php

/**
 * Student
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\User;

use Schoolbox\Collection;
use Schoolbox\External\Normalise\User\Common\Account;
use Schoolbox\External\Normalise\User\Common\Address;
use Schoolbox\External\Normalise\User\Common\Phone;
use Schoolbox\External\Normalise\User\Student\Year;
use Schoolbox\External\Normalise\User\Student\Form;
use Schoolbox\External\Normalise\User\Common\House;

class Student extends Base
{
    /* Personal
     */

    /**
     * Medical requirements
     *
     * @var string
     */
    protected $medical = '';

    /**
     * Special needs
     *
     * @var string
     */
    protected $specialNeeds = '';

    /**
     * Alert message
     *
     * @var string
     */
    protected $alert = '';

    /**
     * "LockerBook" whatever that is
     *
     * @var string
     */
    protected $locker = '';


    /**
     * Boarding Status is a bool denoting whether they are a boarder or not
     *
     * @var int|string|null
     */
    protected null|int|string $boardingStatus = null;


    /* Academics
     */

    /**
     * An instance of Year
     *
     * @var Student\Year
     */
    protected $year = null;

    /**
     * An instance of Form
     *
     * @var Student\Form
     */
    protected $form = null;

    /**
     * Tutor
     *
     * @var Tutor
     */
    protected $tutor = null;

    /* Parents
     */

    /**
     * A collection of Guardian instances
     *
     * @var \Schoolbox\Collection
     */
    protected $guardians = null;

    /**
     * The parent(s) of this person are separated
     *
     * @var bool
     */
    protected $guardiansSeparated = false;

    /* Contact
     */

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
        $this->setYear(new Year());
        $this->setForm(new Form());
        $this->setTutor(new Tutor());
        $this->setHouse(new House());
        $this->setAccount(new Account());
        $this->setEmail(new Collection());
        $this->setHomeEmail(new Collection());
        $this->setWorkEmail(new Collection());
        $this->setPostalAddress(new Address());
        $this->setHomeAddress(new Address());
        $this->setPhoneHome(new Phone());
        $this->setPhoneMobile(new Phone());
        $this->setGuardians(new Collection());
        $this->setCampus(new Collection());
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

    /**
     * Set alert
     *
     * @param string $alert
     * @return $this
     */
    public function setAlert($alert)
    {
        $this->alert = $alert;
        return $this;
    }

    /**
     * Get alert
     *
     * @return string
     */
    public function getAlert()
    {
        return $this->alert;
    }

    /**
     * Set form
     *
     * @param Form $form
     * @return $this
     */
    public function setForm($form)
    {
        $this->form = $form;
        return $this;
    }

    /**
     * Get form
     *
     * @return Form
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * Set guardians
     *
     * @param Collection $guardians
     * @return $this
     */
    public function setGuardians($guardians)
    {
        $this->guardians = $guardians;
        return $this;
    }

    /**
     * Get guardians
     *
     * @return \Schoolbox\Collection
     */
    public function getGuardians()
    {
        return $this->guardians;
    }

    /**
     * Set guardians separated flag
     *
     * @param boolean $guardiansSeparated
     * @return $this
     */
    public function setGuardiansSeparated($guardiansSeparated)
    {
        $this->guardiansSeparated = $guardiansSeparated;
        return $this;
    }

    /**
     * Get guardians separated flag
     *
     * @return boolean
     */
    public function getGuardiansSeparated()
    {
        return $this->guardiansSeparated;
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

    /**
     * Set medical
     *
     * @param string $medical
     * @return $this
     */
    public function setMedical($medical)
    {
        $this->medical = $medical;
        return $this;
    }

    /**
     * Get medical
     *
     * @return string
     */
    public function getMedical()
    {
        return $this->medical;
    }

    /**
     * Set special needs
     *
     * @param string $specialNeeds
     * @return $this
     */
    public function setSpecialNeeds($specialNeeds)
    {
        $this->specialNeeds = $specialNeeds;
        return $this;
    }

    /**
     * Get special needs
     *
     * @return string
     */
    public function getSpecialNeeds()
    {
        return $this->specialNeeds;
    }

    /**
     * Set tutor
     *
     * @param Tutor $tutor
     * @return $this
     */
    public function setTutor($tutor)
    {
        $this->tutor = $tutor;
        return $this;
    }

    /**
     * Get tutor
     *
     * @return Tutor
     */
    public function getTutor()
    {
        return $this->tutor;
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
     * @return int|string|null
     */
    public function getBoardingStatus(): int|string|null
    {
        return $this->boardingStatus;
    }


    /**
     * @param int|string|null $boardingStatus
     * @return Student
     */
    public function setBoardingStatus(int|string|null $boardingStatus): self
    {
        $this->boardingStatus = $boardingStatus;
        return $this;
    }
}
