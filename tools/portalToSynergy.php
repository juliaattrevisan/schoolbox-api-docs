<?php

require_once dirname(__FILE__) . '/../conf/conf.php';
$_SERVER['DOCUMENT_ROOT'] = '/usr/local/vhosts/portal/www';
define(SITE_root, '/usr/local/vhosts/portal');

require_once SITE_root.'/library/user.php';
require_once SITE_root.'/library/group.php';
require_once SITE_root.'/www/include/stdfuncs.php';

$userHandler = new User;
$groupHandler = new Group(TUTORIAL_SERVICE);

ini_set("auto_detect_line_endings", 1);

print "<pre>\n";

$handle = fopen("./portal_ids.csv", "r");
while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
	
	$query = "SELECT * FROM user WHERE id = ".(int)$data[0];
	$user = $userHandler->db->getRow($query);
	
	print trim($data[0]).",";
	if (isset($user['id'])) {
		//print formatName($user)."\n";
		print $user['synergy_id']."\n";
		//$groupHandler->addUser(0, $user['uid'], WRITE_ACCESS);
	} else
		print "N/A\n";
}
print "</pre>";
?>
