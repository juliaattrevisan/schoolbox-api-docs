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
     * @param Account $account
     * @param Address $postalAddress
     * @param Address $homeAddress
     * @param $email
     * @param $extension
     * @param Phone $phoneHome
     * @param Phone $phoneMobile
     * @param Phone $phoneWork
     * @param Collection $campus
     * @param Type $type
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
        Account $account,
        Address $postalAddress,
        Address $homeAddress,
        $email,
        $extension,
        Phone $phoneHome,
        Phone $phoneMobile,
        Phone $phoneWork,
        Collection $campus,
        Type $type,
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
        $this->setAccount($account);
        $this->setPostalAddress($postalAddress);
        $this->setHomeAddress($homeAddress);
        $this->setEmail($email);
        $this->setExtension($extension);
        $this->setPhoneHome($phoneHome);
        $this->setPhoneMobile($phoneMobile);
        $this->setPhoneWork($phoneWork);
        $this->setCampus($campus);
        $this->setType($type);
        $this->setPubliclyVisible($publiclyVisible);
    }

    /* Getters/Setters
     */

    /**
     * Set phone work
     *
     * @param Phone $phoneWork
     */
    protected function setPhoneWork($phoneWork)
    {
        $this->phoneWork = $phoneWork;
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
     */
    protected function setCampus($campus)
    {
        $this->campus = $campus;
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
     */
    protected function setExtension($extension)
    {
        $this->extension = $extension;
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
     */
    protected function setType($type)
    {
        $this->type = $type;
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