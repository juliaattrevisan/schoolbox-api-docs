<?php

require_once '../../../conf/conf.php';
$_SERVER['DOCUMENT_ROOT'] = SITE_root . '/www';

require_once SITE_root.'/library/user.php';
require_once SITE_root.'/library/group.php';
require_once SITE_root.'/www/include/stdfuncs.php';

$userHandler = new User;
$groupHandler = new Group(TUTORIAL_SERVICE);

$handle = fopen($argv[1], "r");
while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
	$user = $userHandler->getBySynergy($data[0]);
	if (isset($user['uid'])) {
		print formatName($user)."\n";
		$groupHandler->addUser(0, $user['uid'], WRITE_ACCESS);
	}
}

?>