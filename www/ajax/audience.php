<?php

use Schoolbox\Role;
use Schoolbox\UserMapper;

require_once dirname(__FILE__) . '/../../library/Bootstrap.php';

//First lets grab some roles
$roleMapper =  \Schoolbox\RoleMapper::getInstance();

/** @var \Schoolbox\Collection|Role[] $roles */
$roles =  $roleMapper->findAll();


//--------------------------------------- GROUPS/HOMEPAGES
$page = new SitePage();
$categoryMapper = new \Schoolbox\Resource\Folder\Mapper();

$suggestions = array();
$data = array();

$allResults = $categoryMapper->searchByName($_GET['query']);

$return = array();

//Include all the folders by themself
foreach ($allResults as $currentResult) {
    $return[] = array(
        'id'   => 'folder_'.$currentResult->getId(),
        'name' => $currentResult->getName() . ' - /homepage/' . $currentResult->getId(),
        'type' => 'homepage'
    );
}

//Also include them with roles attached
foreach ($allResults as $currentResult) {
    foreach ($roles as  $role) {
        $return[] = array(
            'id'   => 'role_'.$role->getName().'_inFolder_'.$currentResult->getId(),
            'name' => $role->getName().' in '.$currentResult->getName().' - /homepage/' . $currentResult->getId(),
            'type' => 'homepage'
        );

        if($role->getType() == 'parent') {
            $return[] = array(
                'id'   => 'role_'.$role->getName().'_ofFolder_'.$currentResult->getId(),
                'name' => $role->getName().' of '.$currentResult->getName().' - /homepage/' . $currentResult->getId(),
                'type' => 'homepage'
            );
        }
    }
}


//----------------------------------------- USERS

$userHandler = new User();
$groupHandler = new Group();
$pageHandler = new SitePage();
$emailObj = new Email();
$userMapper = UserMapper::getInstance();

$result = $suggestions = $data = [];

$excludeID = $_GET['excludeID'];
$keyword = $_GET['query'];
$groupID = $_GET['groupID'];
$roleTypeID = $_GET['roleTypeID'];
$showCampus = !empty($_GET['showCampus']);
$residentialHouseID = $_GET['residentialHouseID'];

if (!empty($residentialHouseID)) {
    $residentialHouseID = explode(';', $residentialHouseID);
}

$filterParents = false;

if (is_string($keyword) && strlen($keyword) > 1 && strlen($keyword) < 64 && (strlen($groupID) || strlen($roleTypeID))) {

    /** @var \Schoolbox\Collection | \Schoolbox\User[] $userList */
    $userList = $userMapper->findByNameAndGroup($keyword, [1,2,3,4,5,6]);

    $studentPrefixes =  ["Parents of ", "Teachers of "];

    foreach ($userList as $user) {
        $return[] = [
            'id'   => 'user_'.$user->getId(),
            'name' => $user->getFullName(),
        ];

        if($user->getRole()->getType() == 'student') {
            foreach ( $studentPrefixes as $studentPrefix) {
                $return[] = [
                    'id'   => $studentPrefix.'_user_'.$user->getId(),
                    'name' => $studentPrefix.$user->getFullName(),
                ];
            }
        }
    }





//    if (strlen($roleTypeID)) {
//
//        // Search in a list of role types.
//
//        $groupID = [];
//
//        foreach (explode(';', strtolower($roleTypeID)) as $roleType) {
//            $thisRoleGroups = [];
//            switch ($roleType) {
//                case Role::STAFF:
//                    $thisRoleGroups = $groupHandler->getStaffGroupTypeIds();
//                    break;
//                case 'parentofstudent':
//                    $filterParents = true;
//                    $thisRoleGroups = $groupHandler->getParentGroupTypeIds();
//                    break;
//                case Role::PARENT:
//                    $thisRoleGroups = $groupHandler->getParentGroupTypeIds();
//                    break;
//                case Role::STUDENT:
//                    $thisRoleGroups = $groupHandler->getStudentGroupTypeIds();
//                    break;
//                case Role::GUEST:
//                    $thisRoleGroups = $groupHandler->getGuestGroupTypeIds();
//                    break;
//            }
//
//            $groupID = array_merge($groupID, $thisRoleGroups);
//        }
//
//    } elseif (strstr($groupID, ';')) {
//
//        // Search in a list of role types.
//
//        $groupID = explode(';', $groupID);
//
//    } else {
//
//        // Search group 0 (all groups)
//
//        $groupID = 0;
//    }
//    /** @var \Schoolbox\Collection | \Schoolbox\User[] $userList */
//    $userList = $userHandler->ajaxSearch(
//        $keyword,
//        $groupID,
//        $residentialHouseID,
//        30
//    );
//
//    $student = null;
//
//    // If we are getting parents of a student, load the student and populate their parents
//
//    if ($filterParents) {
//        $student = $userMapper->findById($_GET["studentId"]);
//        if ($student) {
//            $student->loadParents();
//        }
//    }
//
//    if (is_array($userList)) {
//        foreach ($userList as $user) {
//
//            if ($user['uid'] == $excludeID) {
//                continue;
//            }
//
//            // If required, filter to parents of the selected child
//
//            if ($filterParents && $student != null) {
//                if ($userSchoolbox = $userMapper->findById($user["uid"])) {
//                    if ($userSchoolbox->getRole()->isParent()) {
//                        if (!$userSchoolbox->isParentOf($student)) {
//                            continue;
//                        }
//                    }
//                }
//            }
//
//            if ($_GET['email']) {
//                $email = $emailObj->formatEmailFromArray($user);
//
//                if ($email) {
//                    $fullEmail = $user['firstname'] . " " . $user['lastname'] . ' <' . $email . '>';
//                    $return[] = [
//                        'name' => $fullEmail,
//                        'id' => $fullEmail,
//                    ];
//                }
//            } else {
//
//                $person = "{$user['firstname']} {$user['lastname']} - ";
//
//                if ($user['year']) {
//                    $person .= "Year {$user['year']}, ";
//                }
//
//                if ($showCampus) {
//                    $person .= ' ' . $user['campusName'] . ' ';
//                }
//
//                $person .= $user['gname'];
//
//                if ($user['synergy_id']) {
//                    $person .= " ({$user['synergy_id']})";
//                }
//
//                $return[] = [
//                    'name' => $person,
//                    'id' => 'user_'.intval($user['uid']),
//                    'first_name' => $user['firstname'],
//                    'last_name' => $user['lastname'],
//                    'role' => $user['gname'],
//                    'external_id' => $user['synergy_id'],
//                ];
//
////                $studentPrefixes = ["Teachers", 'Parents'];
////
////                if($user->getRole()->getType() == 'STUDENT') {
////
////                    foreach($studentPrefixes as $prefix) {
////                        $return[] = [
////                            'name' => $prefix.'of'.$person,
////                            'id' => $prefix.'_user'_'.intval($user['uid']),
////                            'first_name' => $user['firstname'],
////                            'last_name' => $user['lastname'],
////                            'role' => $user['gname'],
////                            'external_id' => $user['synergy_id'],
////                        ];
////                    }
////
////                }
//
//            }
//        }
//    }
}


header('Content-type: application/json');
echo json_encode($return);