<?php
/**
 * Staff
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\Admin;
use Schoolbox\External\Normalise\User\Adapter;
use Schoolbox\Collection;

class Staff extends Base {

    /**
     * Generate a list of all people
     */
    public function generateAllPeople() {
        $this->setAllPeople($this->getAdapter()->getStaffUserAccountInfo());
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
                if (strlen($currentCampus->getName())) {
                    $campus[$currentCampus->getName()] = $currentCampus;
                }
            }
        }

        $this->setExternalCampus(new Collection(array_values($campus)));

        /* Find a list of all the roles
         */
        $roles = array();

        foreach($this->getAllPeople() as $currentPeople) {
            if (strlen($currentPeople->getMeta()->getType())) {
                $roles[$currentPeople->getMeta()->getType()] = $currentPeople->getMeta();
            }
        }

        $this->setExternalRoles(new Collection(array_values($roles)));

        /* Schoolbox lists
         */
        $this->setSchoolboxRoles($this->getRoleMapper()->findByColumn('group_type', 'staff'));
        $this->setSchoolboxCampus($this->getCampusMapper()->findAll());
        $this->setSchoolboxYear($this->getRoleMapper()->findAll());
        $this->setSchoolboxHouse($this->getHouseMapper()->findAll());
    }
}