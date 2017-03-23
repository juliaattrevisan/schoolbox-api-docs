<?php
require_once dirname(__FILE__) . '/../../library/Bootstrap.php';
require_once SITE_root . '/www/include/stdfuncs.php';

$userHandler = new User();
$groupHandler = new Group();
$pageHandler = new SitePage();
$emailObj = new Email();
$userMapper = \Schoolbox\UserMapper::getInstance();

$result = $suggestions = $data = $return = array();

$keyword            = $_GET['query'];
$groupID            = $_GET['groupID'];
$roleTypeID         = $_GET['roleTypeID'];
$showCampus         = !empty($_GET['showCampus']);
$residentialHouseID = $_GET['residentialHouseID'];
if (!empty($residentialHouseID)) {
    $residentialHouseID = explode(';', $residentialHouseID);
}

$filterParents = false;

if (is_string($keyword) && strlen($keyword) > 1 && strlen($keyword) < 64
    && (strlen($groupID) || strlen($roleTypeID))
) {
    if (strlen($roleTypeID)) {
        // Search in a list of role types.
        $groupID = array();
        foreach (explode(';', strtolower($roleTypeID)) as $roleType) {
            $thisRoleGroups = array();
            switch ($roleType) {
                case \Schoolbox\Role::STAFF:
                case 'staff':
                    $thisRoleGroups = $groupHandler->getStaffGroupTypeIds();
                    break;
                case 'parentofstudent':
                    $filterParents = true;
                    $thisRoleGroups = $groupHandler->getParentGroupTypeIds();
                    break;
                case \Schoolbox\Role::PARENT:
                case 'parent':
                    $thisRoleGroups = $groupHandler->getParentGroupTypeIds();
                    break;
                case \Schoolbox\Role::STUDENT:
                case 'student':
                    $thisRoleGroups = $groupHandler->getStudentGroupTypeIds();
                    break;
                case \Schoolbox\Role::GUEST:
                case 'guest':
                    $thisRoleGroups = $groupHandler->getGuestGroupTypeIds();
                    break;
            }
            $groupID = array_merge($groupID, $thisRoleGroups);
        }
    }
    elseif (strstr($groupID, ';')) {
        // Search in a list of role types.
        $groupID = explode(';', $groupID);
    }
    else {
        // Search group 0 (all groups)
        $groupID = 0;
    }

    $userList = $userHandler->ajaxSearch(
        $keyword,
        $groupID,
        $residentialHouseID,
        30
    );

    $student = null;

    // If we are getting parents of a student, load the student and populate their parents

    if ($filterParents) {
        $student = $userMapper->findById($_GET["studentId"]);
        if ($student) {
            $student->loadParents();
        }
    }

    if (is_array($userList)) {
        foreach ($userList as $user) {

            // If required, filter to parents of the selected child

            if ($filterParents && $student != null) {
                if ($userSchoolbox = $userMapper->findById($user["uid"])) {
                    if ($userSchoolbox->getRole()->isParent()) {
                        if (!$userSchoolbox->isParentOf($student)) {
                            continue;
                        }
                    }
                }
            }

            if ($_GET['email']) {
                $email = $emailObj->formatEmailFromArray($user);

                if ($email) {
                    $fullEmail = $user['firstname'] . " " . $user['lastname'] . ' <' . $email . '>';
                    $return[] = array(
                        'name' => $fullEmail,
                        'id'   => $fullEmail,
                    );
                }
            } else {

                $person = "{$user['firstname']} {$user['lastname']} - ";

                if ($user['year']) {
                    $person .= "Year {$user['year']}, ";
                }

                if ($showCampus) {
                    $person .= ' '.$user['campusName'].' ';
                }

                $person .= $user['gname'];

                if ($user['synergy_id']) {
                    $person .= " ({$user['synergy_id']})";
                }

                $return[] = array(
                    'name'          => $person,
                    'id'            => intval($user['uid']),
                    'first_name'    => $user['firstname'],
                    'last_name'     => $user['lastname'],
                    'role'          => $user['gname'],
                    'external_id'   => $user['synergy_id']
                );
            }
        }
    }
}

header('Content-type: application/json');
echo json_encode($return);
