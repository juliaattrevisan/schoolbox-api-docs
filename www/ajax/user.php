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
/** @var SitePage $pageHandler */
$pageHandler = $kernel->get('sitePage');
$pageHandler->initSession();

$userMapper = UserMapper::getInstance();

$result = $suggestions = $data = $return = [];

$excludeID = isset($_GET['excludeID']) ? intval($_GET['excludeID']) : null;
$keyword = $_GET['query'] ?? null;
$groupID = $_GET['groupID'] ?? null;
$roleTypeID = $_GET['roleTypeID'] ?? null;
// The showCampus parameter does nothing now default campuses are gone.
// It may be restored later, but don't count on it.
//$showCampus = !empty($_GET['showCampus']);
$showCampus = false;
$residentialHouseID = $_GET['residentialHouseID'] ?? null;
$showEmail = $_GET['email'] ?? false;

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
    $userCollection = $userMapper->createCollection();

    // If groupID is empty array, we should not return any users
    // If groupID = [0], we should return all matching users regardless of role
    if (!empty($groupID)) {
        $userList = $userHandler->ajaxSearch(
            $keyword,
            $groupID,
            $residentialHouseID,
            30
        );
        if ($userList && ($showEmail || $filterParents)) {
            $userCollection = $userMapper->findByColumn('id', array_column($userList, 'uid'))->indexBy('id');
        }
    }

    $student = null;

    // If we are getting parents of a student, load the student and populate their parents

    if ($filterParents) {
        try {
            $student = $userMapper->findById($_GET["studentId"] ?? null);
            $student->loadParents();
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop
        }
    }

    if (is_array($userList)) {
        foreach ($userList as $user) {
            $userId = intval($user['uid']);
            if ($userId === $excludeID) {
                continue;
            }

            // If required, filter to parents of the selected child

            if ($filterParents && $student != null) {
                /** @var \Schoolbox\User|null $userSchoolbox */
                $userSchoolbox = $userCollection->offsetGet($userId);
                if ($userSchoolbox) {
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

            if ($showEmail) {
                $emailVisible ??= new ValidEmailVisible($kernel->get('externalUser'), $kernel->get('config'));
                /** @var \Schoolbox\User|null $userSchoolbox */
                $userSchoolbox = $userCollection->offsetGet($userId);
                if ($userSchoolbox && $emailVisible->isSatisfiedBy($userSchoolbox, $_SESSION['user'])) {
                    if ($userSchoolbox->getEmail()) {
                        $return[] = [
                            'name' => $userSchoolbox->getFormattedEmailDisplay(),
                            'id'   => $userSchoolbox->getFormattedEmailDisplay(),
                        ];
                    }
                }
            } else {
                $fullName = $userHandler->formatName($user);
                $person = "{$fullName} - ";

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
                    'id' => $userId,
                    'first_name' => $user['firstname'],
                    'last_name' => $user['lastname'],
                    'full_name' => $fullName,
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
