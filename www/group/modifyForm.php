<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';

$page = new SitePage;
$groupHandler = new Group(HOMEPAGE_SERVICE);
$categoryHandler = new Category;

if (!is_numeric($_REQUEST['categoryID']))
	error(2);

// check access
if($groupHandler->getPermission($_REQUEST['categoryID']) < FULL_ACCESS)
	error(19);

if (is_numeric($_GET['type'])) {
	// change group type
	$groupHandler->setType($_GET['categoryID'], $_GET['type']);
} 
elseif (is_numeric($_GET['write']) && is_numeric($_GET['userID'])) {
	// change write permission
	$groupHandler->setUserPermission($_GET['categoryID'], $_GET['userID'], $_GET['write']);
}
elseif (isset($_REQUEST['delete'])) {
	// delete user
	if (!is_array($_REQUEST['delete']))
		$_REQUEST['delete'] = array($_REQUEST['delete']);

	if (isset($_REQUEST['service']))
		$groupHandler = new Group($_REQUEST['service']);

	$total = count($_REQUEST['delete']);
	for ($i = 0; $i < $total; $i++) {
		if (is_numeric($_REQUEST['delete'][$i]))
			$groupHandler->deleteUser($_REQUEST['categoryID'], $_REQUEST['delete'][$i]);
	}
	
	$page->backToTheFuture(1);
}
elseif (isset($_REQUEST['accept'])) {
	// accept user
	if (!is_array($_REQUEST['accept']))
		$_REQUEST['accept'] = array($_REQUEST['accept']);
		
	$total = count($_REQUEST['accept']);
	for ($i = 0; $i < $total; $i++) {
		if (is_numeric($_REQUEST['accept'][$i]))
			$groupHandler->setUserPermission($_REQUEST['categoryID'], $_REQUEST['accept'][$i], READ_ACCESS);
	}
	
	localRedir('/group/modify/' . $_REQUEST['categoryID']);
}
elseif (isset($_POST['userID'])) {
	// add user
	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
	$userHandler = new User;
	$user = $userHandler->getByID($_POST['userID']);
	if (isset($user['uid'])) 
		$groupHandler->addUser($_GET['categoryID'], $user['uid']);

	localRedir('/group/modify/' . $_GET['categoryID']);

}
elseif (is_numeric($_GET['owner'])) {
	$category = $categoryHandler->getByID($_REQUEST['categoryID']);
	
	if (!isset($category['id']))
		error(2);


	//check if member is allowed to become a owner
	if(!$groupHandler->allowOwner($_GET['owner']))
		error(30);
	
	//if we are accessing group for first time create group
	if(!$groupHandler->groupExists($_GET['categoryID']))
		$groupHandler->createGroup($_GET['categoryID']);
	
	// demote current owner
	if(is_numeric($category['owner']))
		$groupHandler->setUserPermission($_GET['categoryID'], $category['owner'], WRITE_ACCESS);
	
	// promote new owner
	$groupHandler->setUserPermission($_GET['categoryID'], $_GET['owner'], FULL_ACCESS);

	// re-check permissions, redirect user changing ownership to homepage if they have lost ownership
	if($groupHandler->getPermission($_GET['categoryID']) < FULL_ACCESS)
		localRedir("/homepage/".$_GET['categoryID']);
	else
		localRedir("/group/modify/".$_GET['categoryID']);
}
elseif (isset($_GET['disband'])) {
	$groupHandler->disbandGroup($_GET['categoryID']);
	localRedir("/");
}
elseif (isset($_POST['bulkAdd'])) {
	//get rid of newlines
	$string = preg_replace("(\r\n|\n|\r)", ",", $_POST['bulkAdd']);
	
	//split the list into an array
	$list = preg_split('[,; ]', $string);
	
	foreach($list as $key) {
		trim($key);
		if(is_numeric($key)) {
			//numeric assume its a synergy id
			$query = "SELECT id FROM user WHERE synergy_id = $key";
			$userid = $groupHandler->db->getRow($query);
			if($userid['id']) 
				$groupHandler->addUser($_REQUEST['categoryID'], $userid['id']);
				
		} else {
			//word, assume its a username
			$query = "SELECT id FROM user WHERE username like '$key'";
			$userid = $groupHandler->db->getRow($query);
			if($userid['id'])
				$groupHandler->addUser($_REQUEST['categoryID'], $userid['id']);
		}
	}
	
	localRedir('/group/modify/' . $_REQUEST['categoryID']);
}
?>