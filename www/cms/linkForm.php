<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/link.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$page = new SitePage;
$linkHandler = new Link;
$groupHandler = new Group();

if($_POST['url'] && $_POST['description'] && is_numeric($_POST['category'])) {

	if ($groupHandler->getPermission($_POST['category']) < WRITE_ACCESS)
		error(6);

	$link = array(	'url'			=> $_POST['url'],
					'description'	=> $_POST['description'],
					'comment'		=> $_POST['comment'],
					'hidden'		=> $_POST['hidden'],
				);

	if(is_numeric($_POST['linkID'])) {
		
		/* Modify existing link */
		
		/* Double check permission on link */
		$currentLink = $linkHandler->getById($_POST['linkID']);
		if ($groupHandler->getPermission($currentLink['cid'] < WRITE_ACCESS))
			error(6);
		
		$link['id'] = $_POST['linkID'];
		$linkHandler->update($link);
		
		$page->backToTheFuture(2);
	}
	else {
		/* Add link */
		$link['category'] = $_POST['category'];
		$link['owner'] = $_SESSION['id'];
		
		$linkHandler->create($link);
		
		$page->backToTheFuture(2);
	}
}
else if(is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
	
	/* Move link from one category to another, after checking permission on both from and to categories */
	if(!$link = $linkHandler->getByID($_POST['move']))
		echo "Failed";

	if ($groupHandler->getPermission($link['cid']) < WRITE_ACCESS)
		echo "Insufficient permssions";

	if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS)
		echo "Insufficient permssions";
	
	$linkHandler->update(array('id'=>$_POST['move'], 'category'=> $_POST['categoryID']));

}
else if($_GET['delete']) {

	if(!$link = $linkHandler->getByID($_GET['delete']))
		error(2);

	if ($groupHandler->getPermission($link['cid']) < WRITE_ACCESS)
		error(6);

	$linkHandler->delete($_GET['delete']);
	
	$page->backToTheFuture(1);
}
else {
	error(2);
}



?>
