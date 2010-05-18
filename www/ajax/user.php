<?php

require_once dirname(__FILE__) . '/../conf/conf.php';
require_once SITE_root . '/www/include/stdfuncs.php';
require_once SITE_root . '/library/user.php';
$userHandler = new User;
$result = $suggestions = $data = array();
$keyword = $_GET['query'];
$groupID = $_GET['groupID'];

if (is_string($keyword) && strlen($keyword) > 1 && strlen($keyword) < 64 && is_numeric($groupID)) {
	$userList = $userHandler->ajaxSearch($keyword, $groupID, 30);
	if (is_array($userList)) {
		foreach ($userList as $user) {
			
            if($_GET['email']) {
                $email = formatEmail($user);
                if($email) {
                    $suggestions[] = scrubText($user['firstname']." ".$user['lastname'].' <'.$email.'>');
                    $data[] = scrubText($user['firstname']." ".$user['lastname']).' <'.$email.'>';
                }
            }
            else {
                $suggestions[] = scrubText($user['firstname']." ".$user['lastname']);
                $data[] = $user['uid'];
            }
		}
	}
}

$result = array('query'	=> $_GET['query'],
				'suggestions'	=> $suggestions,
				'data'	=> $data,
				);

header('Content-type: application/json');
echo json_encode($result);

?>