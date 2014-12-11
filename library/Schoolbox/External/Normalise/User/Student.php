<?php
/**
 * Student
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User;
use Schoolbox\Collection;
use Schoolbox\External\Normalise\User\Common\Account;
use Schoolbox\External\Normalise\User\Common\Address;
use Schoolbox\External\Normalise\User\Common\Phone;
use Schoolbox\External\Normalise\User\Student\Year;
use Schoolbox\External\Normalise\User\Student\Form;
use Schoolbox\External\Normalise\User\Common\House;

class Student extends Base {

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
     * @var Staff
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
     *
     * Immutable
     *
     * @param $id
     * @param $title
     * @param $givenName
     * @param $preferredName
     * @param $lastname
     * @param $gender
     * @param $dob
     * @param $portrait
     * @param $medical
     * @param $specialNeeds
     * @param $alert
     * @param $locker
     * @param Student\Year $year
     * @param Student\Form $form
     * @param Staff $tutor
     * @param House $house
     * @param Account $account
     * @param Address $postalAddress
     * @param Address $homeAddress
     * @param $email
     * @param Phone $phoneHome
     * @param Phone $phoneMobile
     * @param \Schoolbox\Collection $guardians
     * @param $guardiansSeparated
     * @param Collection $campus
     * @param $publiclyVisible
     */
    public function __construct(
        $id,
        $title,
        $givenName,
        $preferredName,
        $lastname,
        $gender,
        $dob,
        $portrait,
        $medical,
        $specialNeeds,
        $alert,
        $locker,
        Year $year,
        Form $form,
        Staff $tutor,
        House $house,
        Account $account,
        Address $postalAddress,
        Address $homeAddress,
        $email,
        Phone $phoneHome,
        Phone $phoneMobile,
        Collection $guardians,
        $guardiansSeparated,
        Collection $campus,
        $publiclyVisible
    ) {
        $this->setId($id);
        $this->setTitle($title);
        $this->setGivenName($givenName);
        $this->setPreferredName($preferredName);
        $this->setLastname($lastname);
        $this->setGender($gender);
        $this->setDob($dob);
        $this->setPortrait($portrait);
        $this->setMedical($medical);
        $this->setSpecialNeeds($specialNeeds);
        $this->setAlert($alert);
        $this->setLocker($locker);
        $this->setYear($year);
        $this->setForm($form);
        $this->setTutor($tutor);
        $this->setHouse($house);
        $this->setAccount($account);
        $this->setPostalAddress($postalAddress);
        $this->setHomeAddress($homeAddress);
        $this->setEmail($email);
        $this->setPhoneHome($phoneHome);
        $this->setPhoneMobile($phoneMobile);
        $this->setGuardians($guardians);
        $this->setGuardiansSeparated($guardiansSeparated);
        $this->setCampus($campus);
        $this->setPubliclyVisible($publiclyVisible);
    }

    /**
     * Set alert
     *
     * @param string $alert
     */
    protected function setAlert($alert)
    {
        $this->alert = $alert;
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
     * @param \Schoolbox\External\Normalise\User\Student\Form $form
     */
    protected function setForm($form)
    {
        $this->form = $form;
    }

    /**
     * Get form
     *
     * @return \Schoolbox\External\Normalise\User\Student\Form
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * Set guardians
     *
     * @param \Schoolbox\Collection $guardians
     */
    protected function setGuardians($guardians)
    {
        $this->guardians = $guardians;
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
     */
    protected function setGuardiansSeparated($guardiansSeparated)
    {
        $this->guardiansSeparated = $guardiansSeparated;
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
     * @param \Schoolbox\External\Normalise\User\Common\House $house
     */
    protected function setHouse($house)
    {
        $this->house = $house;
    }

    /**
     * Get house
     *
     * @return \Schoolbox\External\Normalise\User\Common\House
     */
    public function getHouse()
    {
        return $this->house;
    }

    /**
     * Set locker
     *
     * @param string $locker
     */
    protected function setLocker($locker)
    {
        $this->locker = $locker;
    }

    /**
     * Get locker
     *
     * @return string
     */
    public function getLocker()
    {
        return $this->locker;
    }

    /**
     * Set medical
     *
     * @param string $medical
     */
    protected function setMedical($medical)
    {
        $this->medical = $medical;
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
     */
    protected function setSpecialNeeds($specialNeeds)
    {
        $this->specialNeeds = $specialNeeds;
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
     * @param \Schoolbox\External\Normalise\User\Staff $tutor
     */
    protected function setTutor($tutor)
    {
        $this->tutor = $tutor;
    }

    /**
     * Get tutor
     *
     * @return \Schoolbox\External\Normalise\User\Staff
     */
    public function getTutor()
    {
        return $this->tutor;
    }

    /**
     * Set year
     *
     * @param \Schoolbox\External\Normalise\User\Student\Year $year
     */
    protected function setYear($year)
    {
        $this->year = $year;
    }

    /**
     * Get year
     *
     * @return \Schoolbox\External\Normalise\User\Student\Year
     */
    public function getYear()
    {
        return $this->year;
    }
}