<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\Admin;
use Schoolbox\Collection;
use Schoolbox\RoleMapper;
use Schoolbox\CampusMapper;
use HouseMapper;
use Schoolbox\External\Normalise\User\Adapter;

abstract class Base {

    /**
     * All external campuses
     *
     * @var Collection
     */
    protected $externalCampus = null;

    /**
     * All external years
     *
     * @var Collection
     */
    protected $externalYear = null;

    /**
     * All external houses
     *
     * @var Collection
     */
    protected $externalHouse = null;

    /**
     * All external roles
     *
     * @var Collection
     */
    protected $externalRoles = null;

    /**
     * All schoolbox roles
     *
     * @var Collection
     */
    protected $schoolboxRoles = null;

    /**
     * All schoolbox campuses
     *
     * @var Collection
     */
    protected $schoolboxCampus = null;

    /**
     * All schoolbox roles
     *
     * @var Collection
     */
    protected $schoolboxYear = null;

    /**
     * All schoolbox houses
     *
     * @var Collection
     */
    protected $schoolboxHouse = null;

    /**
     * Role mapper
     *
     * @var \Schoolbox\RoleMapper
     */
    protected $roleMapper = null;

    /**
     * Campus mapper
     *
     * @var \Schoolbox\CampusMapper
     */
    protected $campusMapper = null;

    /**
     * House mapper
     *
     * @var HouseMapper
     */
    protected $houseMapper = null;

    /**
     * All people
     *
     * @var Collection
     */
    protected $allPeople = null;

    /**
     * External User Adapter
     *
     * @var Adapter
     */
    protected $adapter = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->setup();
    }

    /**
     * Setup
     */
    protected function setup() {
        $this->setExternalRoles(new Collection());
        $this->setExternalCampus(new Collection());
        $this->setExternalYear(new Collection());
        $this->setExternalHouse(new Collection());

        $this->setSchoolboxRoles(new Collection());
        $this->setSchoolboxCampus(new Collection());
        $this->setSchoolboxYear(new Collection());
        $this->setSchoolboxHouse(new Collection());

        $this->setRoleMapper(new RoleMapper());
        $this->setCampusMapper(new CampusMapper());
        $this->setHouseMapper(new HouseMapper());

        $this->setAdapter(new Adapter(new \External_User()));

    }

    abstract public function execute();

    /* Getters/Setters
     */

    /**
     * Set external campus
     *
     * @param \Schoolbox\Collection $externalCampus
     */
    public function setExternalCampus($externalCampus)
    {
        $this->externalCampus = $externalCampus;
    }

    /**
     * Get external campus
     *
     * @return \Schoolbox\Collection
     */
    public function getExternalCampus()
    {
        return $this->externalCampus;
    }

    /**
     * Set external house
     *
     * @param \Schoolbox\Collection $externalHouse
     */
    public function setExternalHouse($externalHouse)
    {
        $this->externalHouse = $externalHouse;
    }

    /**
     * Get external house
     *
     * @return \Schoolbox\Collection
     */
    public function getExternalHouse()
    {
        return $this->externalHouse;
    }

    /**
     * Set external year
     *
     * @param \Schoolbox\Collection $externalYear
     */
    public function setExternalYear($externalYear)
    {
        $this->externalYear = $externalYear;
    }

    /**
     * Get external year
     *
     * @return \Schoolbox\Collection
     */
    public function getExternalYear()
    {
        return $this->externalYear;
    }

    /**
     * Set schoolbox roles
     *
     * @param \Schoolbox\Collection $schoolboxRoles
     */
    public function setSchoolboxRoles($schoolboxRoles)
    {
        $this->schoolboxRoles = $schoolboxRoles;
    }

    /**
     * Get schoolbox roles
     *
     * @return \Schoolbox\Collection
     */
    public function getSchoolboxRoles()
    {
        return $this->schoolboxRoles;
    }

    /**
     * Set campus mapper
     *
     * @param \Schoolbox\CampusMapper $campusMapper
     */
    public function setCampusMapper($campusMapper)
    {
        $this->campusMapper = $campusMapper;
    }

    /**
     * Get campus m apper
     *
     * @return \Schoolbox\CampusMapper
     */
    public function getCampusMapper()
    {
        return $this->campusMapper;
    }

    /**
     * Set house mapper
     *
     * @param HouseMapper $houseMapper
     */
    public function setHouseMapper($houseMapper)
    {
        $this->houseMapper = $houseMapper;
    }

    /**
     * Get house mapper
     *
     * @return HouseMapper
     */
    public function getHouseMapper()
    {
        return $this->houseMapper;
    }

    /**
     * Set role mapper
     *
     * @param \Schoolbox\RoleMapper $roleMapper
     */
    public function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
    }

    /**
     * Get role mapper
     *
     * @return \Schoolbox\RoleMapper
     */
    public function getRoleMapper()
    {
        return $this->roleMapper;
    }

    /**
     * Set external roles
     *
     * @param \Schoolbox\Collection $externalRoles
     */
    public function setExternalRoles($externalRoles)
    {
        $this->externalRoles = $externalRoles;
    }

    /**
     * Get external roles
     *
     * @return \Schoolbox\Collection
     */
    public function getExternalRoles()
    {
        return $this->externalRoles;
    }

    /**
     * Set schoolbox campus
     *
     * @param \Schoolbox\Collection $schoolboxCampus
     */
    public function setSchoolboxCampus($schoolboxCampus)
    {
        $this->schoolboxCampus = $schoolboxCampus;
    }

    /**
     * Get schoolbox campus
     *
     * @return \Schoolbox\Collection
     */
    public function getSchoolboxCampus()
    {
        return $this->schoolboxCampus;
    }

    /**
     * Set schoolbox year
     *
     * @param null $schoolboxYear
     */
    public function setSchoolboxYear($schoolboxYear)
    {
        $this->schoolboxYear = $schoolboxYear;
    }

    /**
     * Get schoolbox year
     *
     * @return null
     */
    public function getSchoolboxYear()
    {
        return $this->schoolboxYear;
    }

    /**
     * Set schoolbox house
     *
     * @param \Schoolbox\Collection $schoolboxHouse
     */
    public function setSchoolboxHouse($schoolboxHouse)
    {
        $this->schoolboxHouse = $schoolboxHouse;
    }

    /**
     * Get schoolbox house
     *
     * @return \Schoolbox\Collection
     */
    public function getSchoolboxHouse()
    {
        return $this->schoolboxHouse;
    }

    /**
     * Set all people
     *
     * @param \Schoolbox\Collection $allPeople
     */
    public function setAllPeople($allPeople)
    {
        $this->allPeople = $allPeople;
    }

    /**
     * Get all people
     *
     * @return \Schoolbox\Collection
     */
    public function getAllPeople()
    {
        return $this->allPeople;
    }

    /**
     * Set adapter
     *
     * @param \Schoolbox\External\Normalise\User\Adapter $adapter
     */
    public function setAdapter($adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Get adapter
     *
     * @return \Schoolbox\External\Normalise\User\Adapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }
}
