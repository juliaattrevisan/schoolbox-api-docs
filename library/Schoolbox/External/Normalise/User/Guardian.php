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
use Schoolbox\Collection;

class Guardian extends Base {

    /* Contact
     */

    /**
     * Work phone
     *
     * @var \Schoolbox\External\Normalise\User\Common\Phone
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
     * @param Common\Account $account
     * @param Common\Address $postalAddress
     * @param Common\Address $homeAddress
     * @param $email
     * @param Common\Phone $phoneHome
     * @param Common\Phone $phoneMobile
     * @param Common\Phone $phoneWork
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
        Common\Account $account,
        Common\Address $postalAddress,
        Common\Address $homeAddress,
        $email,
        Common\Phone $phoneHome,
        Common\Phone $phoneMobile,
        Common\Phone $phoneWork,
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
        $this->setAccount($account);
        $this->setPostalAddress($postalAddress);
        $this->setHomeAddress($homeAddress);
        $this->setEmail($email);
        $this->setPhoneHome($phoneHome);
        $this->setPhoneMobile($phoneMobile);
        $this->setPhoneWork($phoneWork);
        $this->setCampus($campus);
        $this->setPubliclyVisible($publiclyVisible);
    }

    /* Getters/Setters
     */

    /**
     * Set phone work
     *
     * @param \Schoolbox\External\Normalise\User\Common\Phone $phoneWork
     */
    protected function setPhoneWork($phoneWork)
    {
        $this->phoneWork = $phoneWork;
    }

    /**
     * Get phone work
     *
     * @return \Schoolbox\External\Normalise\User\Common\Phone
     */
    public function getPhoneWork()
    {
        return $this->phoneWork;
    }
}