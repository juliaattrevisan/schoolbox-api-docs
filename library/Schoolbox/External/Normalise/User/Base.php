<?php
/**
 * User
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
use Schoolbox\External\Normalise\User\Common\Email;
use Schoolbox\Collection;

abstract class Base {

    /* Personal
     */

    /**
     * A unique identifier
     *
     * @var string
     */
    protected $id = '';

    /**
     * Title
     *
     * @var string
     */
    protected $title = '';

    /**
     * Firstname
     *
     * @var string
     */
    protected $givenName = '';

    /**
     * Preferred firstname
     *
     * @var string
     */
    protected $preferredName = '';

    /**
     * Lastname
     *
     * @var string
     */
    protected $lastname = '';

    /**
     * Gender
     *
     * @var string
     */
    protected $gender = '';

    /**
     * Date of birth
     *
     * @var \Alaress_Date_Format
     */
    protected $dob = null;

    /**
     * OS filesystem path to the user's portrait
     *
     * @var string
     */
    protected $portrait = '';

    /* Network
     */

    /**
     * User account info
     *
     * @var Common\Account
     */
    protected $account = null;

    /* Contact
     */

    /**
     * An address
     *
     * @var Address
     */
    protected $postalAddress = null;

    /**
     * An address
     *
     * @var Address
     */
    protected $homeAddress = null;

    /**
     * Email address
     *
     * @var \Schoolbox\Collection
     */
    protected $email = '';

    /**
     * Home phone
     *
     * @var Phone
     */
    protected $phoneHome = '';

    /**
     * Mobile phone
     *
     * @var Phone
     */
    protected $phoneMobile = '';

    /**
     * A collection of Common\Campus instances
     *
     * @var \Schoolbox\Collection|Common\Campus[]
     */
    protected $campus = null;

    /* Publicity
     */

    /**
     * Whether or not to display this person in reports, listings
     * Synonymous with DirectoryIncludeFlag
     *
     * @var bool
     */
    protected $publiclyVisible = true;

    /**
     * Delegate method
     *
     * Add an email to the collection
     *
     * @param Email $email
     * @return $this
     */
    public function addEmail($email) {
        if (strlen(trim($email->getAddress()))) {
            $this->getEmail()->add($email);
        }

        return $this;
    }

    /* Getters/Setters
     */

    /**
     * Set account
     *
     * @param Account $account
     * @return $this
     */
    public function setAccount($account)
    {
        $this->account = $account;
        return $this;
    }

    /**
     * Get account
     *
     * @return Account
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * Set campus collection
     *
     * @param Collection|Common\Campus[] $campus
     * @return $this
     */
    public function setCampus($campus)
    {
        $this->campus = $campus;
        return $this;
    }

    /**
     * Get campus collection
     *
     * @return Collection|Common\Campus[]
     */
    public function getCampus()
    {
        return $this->campus;
    }

    /**
     * Set dob
     *
     * @param \Alaress_Date_Format $dob
     * @return $this
     */
    public function setDob($dob)
    {
        $this->dob = $dob;
        return $this;
    }

    /**
     * Get dob
     *
     * @return \Alaress_Date_Format
     */
    public function getDob()
    {
        return $this->dob;
    }

    /**
     * Set email
     *
     * @param Collection $email
     * @return $this
     */
    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Get email
     *
     * @return Collection
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set gender
     *
     * @param string $gender
     * @return $this
     */
    public function setGender($gender)
    {
        $this->gender = $gender;
        return $this;
    }

    /**
     * Get gender
     *
     * @return string
     */
    public function getGender()
    {
        return $this->gender;
    }

    /**
     * Set given name
     *
     * @param string $givenName
     * @return $this
     */
    public function setGivenName($givenName)
    {
        $this->givenName = $givenName;
        return $this;
    }

    /**
     * Get given name
     *
     * @return string
     */
    public function getGivenName()
    {
        return $this->givenName;
    }

    /**
     * Set home address
     *
     * @param Address $homeAddress
     * @return $this
     */
    public function setHomeAddress($homeAddress)
    {
        $this->homeAddress = $homeAddress;
        return $this;
    }

    /**
     * Get home address
     *
     * @return Address
     */
    public function getHomeAddress()
    {
        return $this->homeAddress;
    }

    /**
     * Set id
     *
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get id
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set lastname
     *
     * @param string $lastname
     * @return $this
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;
        return $this;
    }

    /**
     * Get lastname
     *
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * Set phone home
     *
     * @param Phone $phoneHome
     * @return $this
     */
    public function setPhoneHome($phoneHome)
    {
        $this->phoneHome = $phoneHome;
        return $this;
    }

    /**
     * Get phone home
     *
     * @return Phone
     */
    public function getPhoneHome()
    {
        return $this->phoneHome;
    }

    /**
     * Set phone mobile
     *
     * @param Phone $phoneMobile
     * @return $this
     */
    public function setPhoneMobile($phoneMobile)
    {
        $this->phoneMobile = $phoneMobile;
        return $this;
    }

    /**
     * Get phone mobile
     *
     * @return Phone
     */
    public function getPhoneMobile()
    {
        return $this->phoneMobile;
    }

    /**
     * Set portrait
     *
     * @param string $portrait
     * @return $this
     */
    public function setPortrait($portrait)
    {
        $this->portrait = $portrait;
        return $this;
    }

    /**
     * Get portrait
     *
     * @return string
     */
    public function getPortrait()
    {
        return $this->portrait;
    }

    /**
     * Set postal address
     *
     * @param Address $postalAddress
     * @return $this
     */
    public function setPostalAddress($postalAddress)
    {
        $this->postalAddress = $postalAddress;
        return $this;
    }

    /**
     * Get postal address
     *
     * @return Address
     */
    public function getPostalAddress()
    {
        return $this->postalAddress;
    }

    /**
     * Set preferred name
     *
     * @param string $preferredName
     * @return $this
     */
    public function setPreferredName($preferredName)
    {
        $this->preferredName = $preferredName;
        return $this;
    }

    /**
     * Get preferred name
     *
     * @return string
     */
    public function getPreferredName()
    {
        return $this->preferredName;
    }

    /**
     * Set publiclyVisible
     *
     * @param boolean $publiclyVisible
     * @return $this
     */
    public function setPubliclyVisible($publiclyVisible)
    {
        $this->publiclyVisible = $publiclyVisible;
        return $this;
    }

    /**
     * Get publiclyVisible
     *
     * @return boolean
     */
    public function getPubliclyVisible()
    {
        return $this->publiclyVisible;
    }

    /**
     * Set title
     *
     * @param string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }
}