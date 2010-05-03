<?php

require_once dirname(__FILE__) . '/../conf/conf.php';
require_once SITE_root . '/www/include/stdfuncs.php';
require_once SITE_root . '/library/user.php';
$userHandler = new User;
$result = $suggestions = $data = array();
$keyword = $_GET['keyword'];
$groupID = $_GET['groupID'];

if (is_string($keyword) && strlen($keyword) > 1 && strlen($keyword) < 64 && is_numeric($groupID)) {
	$userList = $userHandler->ajaxSearch($keyword, $groupID, 30);
	if (is_array($userList)) {
		foreach ($userList as $user) {
			$suggestions[] = scrubText($user['firstname']." ".$user['lastname']);
			$data[] = $user['uid'];
		}
	}
}

$result = array('query'	=> $_GET['keyword'],
				'suggestions'	=> $suggestions,
				'data'	=> $data,
				);

header('Content-type: application/json');
echo json_encode($result);

?>