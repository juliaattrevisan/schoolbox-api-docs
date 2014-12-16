<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use \Schoolbox\Collection;
use \Schoolbox\External\Normalise\User\Adapter;

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

/* Section - sanitise template loading
 * and load corresponding data
 */
$allPeople = new Collection();
$allCampus = new Collection();
$allYear = new Collection();
$allHouse = new Collection();

$section = strtolower($this->arg[0]);

/* Staff
 */
if ($section == 'staff') {
    $adapter = new Adapter(new \External_User());
    $allPeople = $adapter->getStaffUserAccountInfo();

    /* Find a list of all the campuses currently being used
     */
    $campus = array();

    foreach($allPeople as $currentPeople) {
        foreach($currentPeople->getCampus() as $currentCampus) {
            $campus[$currentCampus->getName()] = $currentCampus;
        }
    }

    $allCampus = new Collection(array_values($campus));
}
/* Student
 */
else if ($section == 'student') {
    $adapter = new Adapter(new \External_User());
    $allPeople = $adapter->getStudentUserAccountInfo();

    /* Find a list of all the campuses currently being used
     */
    $campus = array();

    foreach($allPeople as $currentPeople) {
        foreach($currentPeople->getCampus() as $currentCampus) {
            $campus[$currentCampus->getName()] = $currentCampus;
        }
    }

    $allCampus = new Collection(array_values($campus));

    /* Find a list of all the years currently being used
     */
    $years = array();

    foreach ($allPeople as $currentPeople) {
        $years[$currentPeople->getYear()->getLevel()] = $currentPeople->getYear();
    }

    $allYear = new Collection(array_values($years));

    /* Find a list of all the houses currently being used
     */
    $houses = array();

    foreach ($allPeople as $currentPeople) {
        $houses[$currentPeople->getHouse()->getName()] = $currentPeople->getHouse();
    }

    $allHouse = new Collection(array_values($houses));
}
/* Parent
 */
else if ($section == 'parent') {
    $adapter = new Adapter(new \External_User());
    $allPeople = $adapter->getParentUserAccountInfo();

    /* Find a list of all the campuses currently being used
     */
    $campus = array();

    foreach($allPeople as $currentPeople) {
        foreach($currentPeople->getCampus() as $currentCampus) {
            $campus[$currentCampus->getName()] = $currentCampus;
        }
    }

    $allCampus = new Collection(array_values($campus));
}
/* Default view
 */
else {
    $section = 'intro';
}

/* The Schoolbox Lists
 */
$campusMapper = new \Schoolbox\CampusMapper();
$roleMapper = new \Schoolbox\RoleMapper();
$houseMapper = new HouseMapper();

/* Power to the phplib
 */
$this->setFile(TEMPLATE_content, 'adminv2/synchronise/view.html');

$nav = new AdminV2_Nav();
$nav->parse($this, 'synchronise');

$this->setVar(array(
    'CONTENT' => $this->getDi()->get('templateEngine')->render("adminv2/synchronise/{$section}.twig", array(
        'nav' => array(
            'section' => $section,
        ),
        'data' => array(
            'allPeople' => $allPeople,
            'campus' => array(
                'external' => $allCampus,
                'schoolbox' => $campusMapper->findAll()
            ),
            'year' => array(
                'external' => $allYear,
                'schoolbox' => $roleMapper->findAll()
            ),
            'house' => array(
                'external' => $allHouse,
                'schoolbox' => $houseMapper->findAll()
            )
        ),
    ))
));


