<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once dirname(__FILE__) . '/../../library/Bootstrap.php';

$return = array();
if (isset($_GET['query']) && strlen($_GET['query']) && isset($_GET['groupID']) && strlen($_GET['groupID'])) {
    $groupIds = $_GET['groupID'];

    /* Allow for multiple groups search via autocompletes
     */
    if (strlen($groupIds) && strstr($groupIds, ';')) {
        $groupIdArray = explode(';', $groupIds);
    }
    else if (is_numeric($_GET['groupID'])) {
        $groupIdArray = array($_GET['groupID']);
    }

    $userMapper = new Schoolbox\UserMapper();

    $allStudents = $userMapper->findByNameAndGroup($_GET['query'], $groupIdArray);

    if ($allStudents->count()) {
        foreach($allStudents as $currentStudent) {
            $person = $currentStudent->getFullname() . ' - ';

            if (is_numeric($currentStudent->getYear())) {
                $person .= "Year {$currentStudent->getYear()}, ";
            }

            $person .= $currentStudent->getRole()->getName();

            $return[] = array(
                'id'   => $currentStudent->getId(),
                'name' => $person,
            );
        }
    }
}

header('Content-type: application/json');
echo json_encode($return);
