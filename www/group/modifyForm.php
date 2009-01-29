<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';

$page = new SitePage;
$groupHandler = new Group(HOMEPAGE_SERVICE);
$categoryHandler = new Category;

$category = $categoryHandler->getByID($_REQUEST['categoryID']);

if (!isset($category['id']))
	error(2);
	
// check access
if(!$_SESSION['su'] && $category['owner'] != $_SESSION['id'])
	error(19);

if (is_numeric($_GET['type'])) {
	// change group type
	$data = array('type'	=> $_GET['type'],
				  'cid'		=> $_GET['categoryID'],
				  );
	$groupHandler->db->update('userGroups', 'cid', $data);
} 
elseif (is_numeric($_GET['write']) && is_numeric($_GET['userID'])) {
	// change write permission
	$groupHandler->deleteUser($_GET['userID'], $_GET['categoryID']);
	$groupHandler->addUser($_GET['userID'], $_GET['categoryID'], $_GET['write']);
}
elseif (isset($_REQUEST['delete'])) {
	// delete user
	if (!is_array($_REQUEST['delete']))
		$_REQUEST['delete'] = array($_REQUEST['delete']);
		
	$total = count($_REQUEST['delete']);
	for ($i = 0; $i < $total; $i++) {
		if (is_numeric($_REQUEST['delete'][$i]))
			$groupHandler->deleteUser($_REQUEST['delete'][$i], $_REQUEST['categoryID']);
	}
	
	localRedir('/group/modify/' . $_REQUEST['categoryID']);
}
elseif (isset($_REQUEST['accept'])) {
	// accept user
	if (!is_array($_REQUEST['accept']))
		$_REQUEST['accept'] = array($_REQUEST['accept']);
		
	$total = count($_REQUEST['accept']);
	for ($i = 0; $i < $total; $i++) {
		if (is_numeric($_REQUEST['accept'][$i]))
			$groupHandler->acceptMember($_REQUEST['accept'][$i], $_REQUEST['categoryID']);
	}
	
	localRedir('/group/modify/' . $_REQUEST['categoryID']);
}
elseif (isset($_POST['keyword'])) {
	// add user
	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
	$userHandler = new User;
	$userList = $userHandler->searchUser($_POST['keyword'], 0);
	if (is_array($userList)) 
		$groupHandler->addUser($userList[0]['uid'], $_GET['categoryID']);

	localRedir('/group/modify/' . $_GET['categoryID']);

}
elseif (is_numeric($_GET['owner'])) {
	//check if member is allowed to become a owner
	if(!$groupHandler->allowOwner($_GET['owner']))
		error(30);
	
	//if we are accessing group for first time create group
	if(!$groupHandler->groupExists($_GET['categoryID']))
		$groupHandler->createGroup($_GET['categoryID']);
	
	if(is_numeric($category['owner'])) {
		$groupHandler->deleteUser($category['owner'], $_GET['categoryID']);
		$groupHandler->addUser($category['owner'], $_GET['categoryID'], 2);
	}
	
	//make new person an owner
	$groupHandler->deleteUser($_GET['owner'], $_GET['categoryID']);
	$groupHandler->addUser($_GET['owner'], $_GET['categoryID'], 3);


	//redirect user changing ownership to homepage if they have lost ownership
	if($_SESSION['id'] != $_GET['owner'] && !$_SESSION['su'])
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
	$list = split('[,; ]', $string);
	
	foreach($list as $key) {
		trim($key);
		if(is_numeric($key)) {
			//numeric assume its a synergy id
			$query = "SELECT id FROM user WHERE synergy_id = $key";
			$userid = $groupHandler->db->getRow($query);
			if($userid['id']) 
				$groupHandler->addUser($userid['id'], $_REQUEST['categoryID']);
				
		} else {
			//word, assume its a username
			$query = "SELECT id FROM user WHERE username like '$key'";
			$userid = $groupHandler->db->getRow($query);
			if($userid['id'])
				$groupHandler->addUser($userid['id'], $_REQUEST['categoryID']);
		}
	}
	
	localRedir('/group/modify/' . $_REQUEST['categoryID']);
}
?>