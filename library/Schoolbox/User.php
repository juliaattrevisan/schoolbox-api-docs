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
     * @var ConfigLoader
     */
    protected $config_loader;

    public function __construct() {
        $this->setCampusCollection(new CampusCollection(array()));
        $this->setConfigLoader(new ConfigLoader());
    }

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

    public function setRole($role) {
        $this->role = $role;
    }

    public function getEmail() {
        if($this->localEmail == true) {
            return $this->username .'@'. $this->config_loader->get('site_domain');
        }
        return $this->altEmail;
    }

    public function setAltEmail($altEmail) {
        $this->altEmail = $altEmail;
    }

    public function getAltEmail() {
        return $this->altEmail;
    }

    public function setCalendarView($calendarView) {
        $this->calendarView = $calendarView;
    }

    public function getCalendarView() {
        return $this->calendarView;
    }

    public function setEportfolioEnable($eportfolioEnable) {
        $this->eportfolioEnable = $eportfolioEnable;
    }

    public function getEportfolioEnable() {
        return $this->eportfolioEnable;
    }

    public function setExternalId($externalId) {
        $this->externalId = $externalId;
    }

    public function getExternalId() {
        return $this->externalId;
    }

    public function setFirstname($firstname) {
        $this->firstname = $firstname;
    }

    public function getFirstname() {
        return $this->firstname;
    }

    public function setHash($hash) {
        $this->hash = $hash;
    }

    public function getHash() {
        return $this->hash;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function getId() {
        return $this->id;
    }

    public function setKey($key) {
        $this->key = $key;
    }

    public function getKey() {
        return $this->key;
    }

    public function setLastname($lastname) {
        $this->lastname = $lastname;
    }

    public function getLastname() {
        return $this->lastname;
    }

    public function setLocalEmail($localEmail) {
        $this->localEmail = $localEmail;
    }

    public function getLocalEmail() {
        return $this->localEmail;
    }

    public function setPassword($password) {
        $this->password = $password;
    }

    public function getPassword() {
        return $this->password;
    }

    public function setResidentialHouse($residentialHouse) {
        $this->residentialHouse = $residentialHouse;
    }

    public function getResidentialHouse() {
        return $this->residentialHouse;
    }

    public function setSchoolHouse($schoolHouse) {
        $this->schoolHouse = $schoolHouse;
    }

    public function getSchoolHouse() {
        return $this->schoolHouse;
    }

    public function setSuperuser($superuser) {
        $this->superuser = $superuser;
    }

    public function getSuperuser() {
        return $this->superuser;
    }

    public function setTitle($title) {
        $this->title = $title;
    }

    public function getTitle() {
        return $this->title;
    }

    public function setUseInternalMail($useInternalMail) {
        $this->useInternalMail = $useInternalMail;
    }

    public function getUseInternalMail() {
        return $this->useInternalMail;
    }

    public function setUsername($username) {
        $this->username = $username;
    }

    public function getUsername() {
        return $this->username;
    }

    public function setYear($year) {
        $this->year = $year;
    }

    public function getYear() {
        return $this->year;
    }

    public function setCampusCollection($campusCollection) {
        $this->campusCollection = $campusCollection;
    }

    public function setDefaultCampus($defaultCampus) {
        $this->defaultCampus = $defaultCampus;
    }


    /**
     * @param \Schoolbox\ConfigLoader $config_loader
     */
    public function setConfigLoader($config_loader) {
        $this->config_loader = $config_loader;
    }

    /**
     * @return \Schoolbox\ConfigLoader
     */
    public function getConfigLoader() {
        return $this->config_loader;
    }
}
