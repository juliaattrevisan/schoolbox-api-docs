<?php

/**
 * @author    Someone <support@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Profile\ValidEmailVisible;
use Schoolbox\Role;
use Schoolbox\UserMapper;

// Enforce direct script access
if (isset($this)) {
    /** @var SitePage $this */
    $this->pageNotFound();
}

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$userHandler = new User();
$groupHandler = new Group();
/** @var SitePage $page */
$pageHandler = $kernel->get('sitePage');
$pageHandler->initSession();

$emailObj = new Email();
$userMapper = UserMapper::getInstance();

$result = $suggestions = $data = $return = [];

$excludeID = $_GET['excludeID'] ?? null;
$keyword = $_GET['query'] ?? null;
$groupID = $_GET['groupID'] ?? null;
$roleTypeID = $_GET['roleTypeID'] ?? null;
// The showCampus parameter does nothing now default campuses are gone.
// It may be restored later, but don't count on it.
//$showCampus = !empty($_GET['showCampus']);
$showCampus = false;
$residentialHouseID = $_GET['residentialHouseID'] ?? null;

if (!empty($residentialHouseID)) {
    $residentialHouseID = explode(';', $residentialHouseID);
}

$filterParents = false;

if (is_string($keyword) && strlen($keyword) > 1 && strlen($keyword) < 64 && (strlen($groupID) || strlen($roleTypeID))) {
    if (strlen($roleTypeID)) {
        // Search in a list of role types.

        $groupID = [];

        foreach (explode(';', strtolower($roleTypeID)) as $roleType) {
            $thisRoleGroups = [];
            switch ($roleType) {
                case Role::STAFF:
                    $thisRoleGroups = $groupHandler->getStaffGroupTypeIds();
                    break;
                case 'parentofstudent':
                    $filterParents = true;
                    $thisRoleGroups = $groupHandler->getParentGroupTypeIds();
                    break;
                case Role::PARENT:
                    $thisRoleGroups = $groupHandler->getParentGroupTypeIds();
                    break;
                case Role::STUDENT:
                    $thisRoleGroups = $groupHandler->getStudentGroupTypeIds();
                    break;
                case Role::GUEST:
                    $thisRoleGroups = $groupHandler->getGuestGroupTypeIds();
                    break;
            }

            $groupID = array_merge($groupID, $thisRoleGroups);
        }
    } else {
        $groupID = explode(';', $groupID);
        $groupID = array_map('intval', array_filter($groupID, 'is_numeric'));
    }

    $userList = false;

    // If groupID is empty array, we should not return any users
    // If groupID = [0], we should return all matching users regardless of role
    if (!empty($groupID)) {
        $userList = $userHandler->ajaxSearch(
            $keyword,
            $groupID,
            $residentialHouseID,
            30
        );
    }

    $student = null;

    // If we are getting parents of a student, load the student and populate their parents

    if ($filterParents) {
        $student = $userMapper->findById($_GET["studentId"] ?? null);
        if ($student) {
            $student->loadParents();
        }
    }

    if (is_array($userList)) {
        foreach ($userList as $user) {
            if ($user['uid'] == $excludeID) {
                continue;
            }

            // If required, filter to parents of the selected child

            if ($filterParents && $student != null) {
                if ($userSchoolbox = $userMapper->findById($user["uid"])) {
                    if ($userSchoolbox->getRole()->isParent()) {
                        if (!$userSchoolbox->getChildren()) {
                            $userSchoolbox->setChildren(\Schoolbox\User::createCollection());
                        }
                        if (!$userSchoolbox->isParentOf($student)) {
                            continue;
                        }
                    }
                }
            }

            if ($_GET['email'] ?? null) {
                $emailVisible ??= new ValidEmailVisible($kernel->get('externalUser'), $kernel->get('config'));
                if ($emailVisible->isSatisfiedBy($userMapper->findById($user["uid"]), $_SESSION['user'])) {
                    $email = $emailObj->formatEmailFromArray($user);
                    if ($email) {
                        $fullEmail = $user['firstname'] . " " . $user['lastname'] . ' <' . $email . '>';
                        $return[] = [
                            'name' => $fullEmail,
                            'id'   => $fullEmail,
                        ];
                    }
                }
            } else {
                $person = "{$user['firstname']} {$user['lastname']} - ";

                if ($user['year']) {
                    $person .= "Year {$user['year']}, ";
                }

                if ($showCampus) {
                    $person .= ' ' . $user['campusName'] . ' ';
                }

                $person .= $user['gname'];

                if ($user['synergy_id']) {
                    $person .= " ({$user['synergy_id']})";
                }

                $return[] = [
                    'name' => $person,
                    'id' => intval($user['uid']),
                    'first_name' => $user['firstname'],
                    'last_name' => $user['lastname'],
                    'role' => $user['gname'],
                    'role_id' => $user['gid'],
                    'roleType' => $user['gtype'],
                    'external_id' => $user['synergy_id']
                ];
            }
        }
    }
}

header('Content-type: application/json');
echo json_encode($return);
