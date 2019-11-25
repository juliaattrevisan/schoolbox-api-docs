<?php
/**
 * Student
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\Admin;

use Schoolbox\External\Normalise\User\Adapter;
use Schoolbox\Collection;

class Guardian extends Base {

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
            foreach ($currentPeople->getStudents() as $currentStudent) {
                $years[$currentStudent->getYear()->getLevel()] = $currentStudent->getYear();
            }
        }

        $this->setExternalYear(new Collection(array_values($years)));

        /* Find a list of all the houses currently being used
         */
        $houses = array();

        foreach ($this->getAllPeople() as $currentPeople) {
            foreach ($currentPeople->getStudents() as $currentStudent) {
                if (strlen($currentStudent->getHouse()->getCode())) {
                    $houses[$currentStudent->getHouse()->getCode()] = $currentStudent->getHouse();
                }
            }
        }

        $this->setExternalHouse(new Collection(array_values($houses)));

        /* Schoolbox lists
         */
        $this->setSchoolboxRoles($this->getRoleMapper()->findByColumn('group_type', 'parent'));
        $this->setSchoolboxCampus($this->getCampusMapper()->findAll());
        $this->setSchoolboxYear($this->getRoleMapper()->findAll());
        $this->setSchoolboxHouse($this->getHouseMapper()->findAll());
    }
}
