<?php
/**
 * Student
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\Admin;
use Schoolbox\External\Normalise\User\Adapter;
use Schoolbox\Collection;

class Student extends Base {

    /**
     * Generate a list of all people
     */
    public function generateAllPeople() {
        $this->setAllPeople($this->getAdapter()->getStudentUserAccountInfo());
    }

    /**
     * Generate data
     */
    public function execute() {

        /* Find a list of all the campuses currently being used
         */
        $campus = array();

        foreach($this->getAllPeople() as $currentPeople) {
            foreach($currentPeople->getCampus() as $currentCampus) {
                if (strlen($currentCampus->getCode())) {
                    $campus[$currentCampus->getCode()] = $currentCampus;
                }
            }
        }

        $this->setExternalCampus(new Collection(array_values($campus)));

        /* Find a list of all the years currently being used
         */
        $years = array();

        foreach ($this->getAllPeople() as $currentPeople) {
            $years[$currentPeople->getYear()->getLevel()] = $currentPeople->getYear();
        }

        $this->setExternalYear(new Collection(array_values($years)));

        /* Find a list of all the houses currently being used
         */
        $houses = array();

        foreach ($this->getAllPeople() as $currentPeople) {
            if (strlen($currentPeople->getHouse()->getName())) {
                $houses[$currentPeople->getHouse()->getName()] = $currentPeople->getHouse();
            }
        }

        $this->setExternalHouse(new Collection(array_values($houses)));

        /* Schoolbox lists
         */
        $this->setSchoolboxRoles($this->getRoleMapper()->findByColumn('group_type', 'student'));
        $this->setSchoolboxCampus($this->getCampusMapper()->findAll());
        $this->setSchoolboxYear($this->getRoleMapper()->findAll());
        $this->setSchoolboxHouse($this->getHouseMapper()->findAll());
    }
}