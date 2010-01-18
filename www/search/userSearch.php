<?php

require_once('../../include/sitePage.php');
require_once('../../library/user.php');

$userHandler = new User;

$userList = $userHandler->search($_GET['keyword']);

$userCount = count($userList);

if($userCount == 1) {
	localRedir('/search/user/'.$userList[0]['uid']);
}
else if($userCount > 1) {
	$page = new sitePage();
	$page->setFile(TEMPLATE_content,'user/list.html');
	$page->setTitle('School Directory');
	
	$page->setBlock(TEMPLATE_content, 'userlist', 'userlist_block');
	
	foreach($userList as $user) {
		$outputUser = array('ID'		=> $user['uid'],
							'NAME'		=> formatName($user),
							'YEAR' 		=> $user['year'],
							'GROUP'		=> $user['gname'],
							'EMAIL'		=> formatEmail($user),
							'ON_OFF'  => ++$j%2? '' : 'dark',
							);
		$page->setVar($outputUser);
		$page->parse('userlist_block', 'userlist', true);
	}
	
	$page->outputPage();

}


?>
