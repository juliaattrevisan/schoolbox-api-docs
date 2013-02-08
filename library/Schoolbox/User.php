<?php

/**
 * Generic user object for Schoolbox
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;


class User {

    private $id;
    private $title;
    private $firstname;
    private $lastname;
    private $externalId;
    private $altEmail;
    private $username;
    private $password;
    private $year;
    private $schoolHouse;
    private $residentialHouse;
    private $hash;              //Deprecated: reset password hash
    private $key;               //RSS/VCAL export token
    private $role;              //Users role within the system
    private $campusCollection;            //Collection of campuses
    private $defaultCampus;     //The users default campus
    private $superuser;         //Bool

    //User config options
    private $localEmail;  //Controls if user has local domain email username@domain
    private $eportfolioEnable; //Controls if user has eportfolio
    private $useInternalMail;  //Controls mailto or internal schoolbox mail sending
    private $calendarView; //Controls default calendar view

    /**
     * Test if user is in provided campus
     *
     * @param Campus $campus
     * @return boolean
     */
    public function isInCampus(Campus $campus) {
        return $this->campusCollection->find($campus);
    }

    /**
     * Returns the users default campus
     *
     * @return Campus
     */
    public function getDefaultCampus() {
        return $this->defaultCampus;
    }

    /**
     * Returns a list of collections the user belongs in
     * @return CampusCollection
     */
    public function getCampusCollection() {
        return $this->campusCollection;
    }


    public function isSuperuser() {
        return $this->superuser;
    }

    /**
     * Returns users role, previously known as groups
     *
     * @return Role
     */

    public function getRole() {
        return $this->role;
    }




}
