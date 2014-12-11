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
     * @var string
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
     * @var \Schoolbox\Collection
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
     * Constructor
     */
    public function __construct() {}

    /**
     * Set account
     *
     * @param Account $account
     */
    protected function setAccount($account)
    {
        $this->account = $account;
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
     * @param Collection $campus
     */
    protected function setCampus($campus)
    {
        $this->campus = $campus;
    }

    /**
     * Get campus collection
     *
     * @return Collection
     */
    public function getCampus()
    {
        return $this->campus;
    }

    /**
     * Set dob
     *
     * @param \Alaress_Date_Format $dob
     */
    protected function setDob($dob)
    {
        $this->dob = $dob;
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
     * @param string $email
     */
    protected function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set gender
     *
     * @param string $gender
     */
    protected function setGender($gender)
    {
        $this->gender = $gender;
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
     */
    protected function setGivenName($givenName)
    {
        $this->givenName = $givenName;
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
     */
    protected function setHomeAddress($homeAddress)
    {
        $this->homeAddress = $homeAddress;
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
     */
    protected function setId($id)
    {
        $this->id = $id;
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
     */
    protected function setLastname($lastname)
    {
        $this->lastname = $lastname;
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
     * @param \Schoolbox\External\Normalise\User\Common\Phone $phoneHome
     */
    protected function setPhoneHome($phoneHome)
    {
        $this->phoneHome = $phoneHome;
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
     */
    protected function setPhoneMobile($phoneMobile)
    {
        $this->phoneMobile = $phoneMobile;
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
     */
    protected function setPortrait($portrait)
    {
        $this->portrait = $portrait;
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
     */
    protected function setPostalAddress($postalAddress)
    {
        $this->postalAddress = $postalAddress;
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
     */
    protected function setPreferredName($preferredName)
    {
        $this->preferredName = $preferredName;
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
     */
    protected function setPubliclyVisible($publiclyVisible)
    {
        $this->publiclyVisible = $publiclyVisible;
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
     */
    protected function setTitle($title)
    {
        $this->title = $title;
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