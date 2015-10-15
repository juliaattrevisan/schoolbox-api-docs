<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
require_once SITE_root . '/www/include/stdfuncs.php';

$userHandler = new User();
$groupHandler = new Group();
$pageHandler = new SitePage();
$emailObj = new Email();

$result = $suggestions = $data = $return = array();

$keyword            = $_GET['query'];
$groupID            = $_GET['groupID'];
$roleTypeID         = $_GET['roleTypeID'];
$showCampus         = !empty($_GET['showCampus']);
$residentialHouseID = $_GET['residentialHouseID'];

if (is_string($keyword) && strlen($keyword) > 1 && strlen($keyword) < 64
    && (strlen($groupID) || strlen($roleTypeID))
) {

    if ($groupHandler->isParent() || $groupHandler->isStudent()) {
        // Parents and students can only search for staff, no matter what.
        $groupID = $groupHandler->getStaffGroupTypeIds();
    }
    elseif (strlen($roleTypeID)) {
        // Search in a list of role types.
        $groupID = array();
        foreach (explode(';', strtolower($roleTypeID)) as $roleType) {
            $thisRoleGroups = array();
            switch ($roleType) {
                case \Schoolbox\Role::STAFF:
                case 'staff':
                    $thisRoleGroups = $groupHandler->getStaffGroupTypeIds();
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
        explode(';', $residentialHouseID),
        30
    );

    if (is_array($userList)) {
        foreach ($userList as $user) {

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
                    'name' => $person,
                    'id'   => intval($user['uid']),
                );
            }
        }
    }
}

header('Content-type: application/json');
echo json_encode($return);
