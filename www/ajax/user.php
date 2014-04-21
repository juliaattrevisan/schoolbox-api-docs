<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
require_once SITE_root . '/www/include/stdfuncs.php';

$userHandler = new User();
$groupHandler = new Group();
$pageHandler = new SitePage();
$emailObj = new Email();

$result = $suggestions = $data = $return = array();

$keyword = $_GET['query'];
$groupID = $_GET['groupID'];

if (is_string($keyword) && strlen($keyword) > 1 && strlen($keyword) < 64 && strlen($groupID)) {

    /* Allow for multiple groups search via autocompletes
     */
    if (strstr($groupID, ';')) {
        $groupID = explode(';', $groupID);
    }

    // requires session to know which group we're in
    if ($groupHandler->isParent() || $groupHandler->isStudent()) {
        $groupID = $groupHandler->getStaffGroupTypeIds();
    }

    $userList = $userHandler->ajaxSearch($keyword, $groupID, 30);

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
                    $person .= "Year {$user['year']},";
                }

                $person .= $user['gname'];
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
