<?php

require_once dirname(__FILE__) . '/../conf/conf.php';
require_once SITE_root . '/www/include/stdfuncs.php';
require_once SITE_root . '/library/user.php';
$userHandler = new User;
$result = array();
$keyword = $_POST['keyword'];
$groupID = $_GET['groupID'];

if (is_string($keyword) && strlen($keyword) > 1 && strlen($keyword) < 64 && is_numeric($groupID)) {
	$userList = $userHandler->searchUser($keyword, $groupID);
	if (is_array($userList)) {
		foreach ($userList as $user) {
			$result[] = scrubText($user['firstname']." ".$user['lastname']);
		}
	}
}

header('Content-type: application/json');
echo json_encode($result);

?>