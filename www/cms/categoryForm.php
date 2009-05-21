<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$page = new SitePage;
$categoryHandler = new Category;
$groupHandler = new Group;

if ($_POST['name']) {

	$category = array('name'		=> $_POST['name'],
					  'parent'		=> $_POST['parent'],
					  'template'	=> $_POST['template'],
					  'code'		=> $_POST['code'],
					  'group'		=> $_POST['group'],
					  );

	if (is_numeric($_POST['categoryID'])) {
		if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS)
			error(6);

		$category['id'] = $_POST['categoryID'];
		$categoryHandler->update($category);
	}
	else {
		if ($groupHandler->getPermission($_POST['parent']) < WRITE_ACCESS)
			error(6);
	
		$category['id'] = $categoryHandler->create($category);
		
	}
	
	if($_POST['groupcheck']) {
		/* Setup group and forward to group management page */
		$groupHandler->createGroup($category['id']);
		$groupHandler->addUser($category['id'], $_SESSION['id'], 3);
		localRedir('/group/modify/'.$category['id']);
	} else
		$page->backToTheFuture(2);		
	
}
else if(is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
	
	/* Move link from one category to another, after checking permission on both from and to categories */
	if ($_POST['move'] == $_POST['categoryID'] || !$category = $categoryHandler->getByID($_POST['move']) ) {
		echo "Failed";
		exit;
	}

	if ($groupHandler->getPermission($_POST['move']) < WRITE_ACCESS  || $groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS) {
		echo "Insufficient permssions";
		exit;
	}	
	
	$categoryHandler->update(array('id'=>$_POST['move'], 'parent'=> $_POST['categoryID']));

}
elseif (is_numeric($_GET['delete'])) {

	if(!$category = $categoryHandler->getByID($_GET['delete']))
		error(2);
		
	if ($groupHandler->getPermission($category['id']) < WRITE_ACCESS)
		error(8);
	
	$categoryHandler->delete($_GET['delete']);
	$_SESSION['resourcesID'] = $category['parent'];
	$page->backToTheFuture(1);
}
elseif (is_numeric($_GET['deleteAll'])) {

	if(!$category = $categoryHandler->getByID($_GET['deleteAll']))
		error(2);
			
	$categoryHandler->recursiveDelete($_GET['deleteAll']);
	$_SESSION['resourcesID'] = $category['parent'];
	$page->backToTheFuture(1);
}
else
	error(2);

?>
