<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/wiki.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';

$page = new SitePage;
$wikiHandler = new Wiki;
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (isset($_POST['heading']) && is_numeric($_POST['categoryID'])) {
	
	// check permission
	if ($groupHandler->getPermission($_POST['categoryID']) < READ_ACCESS)
		error(19);	

	$wiki = array('categoryID'	=> $_POST['categoryID'],
				  );
	if (is_numeric($_POST['wikiID'])) {
		$wiki['wikiID'] = $_POST['wikiID'];
		$wikiHandler->update($wiki);
	} else
		$wiki['wikiID'] = $wikiHandler->create($wiki);
	
	$current = $wikiHandler->getByID($wiki['wikiID']);
	$revision = array('wikiID'	=> $wiki['wikiID'],
					  'heading'	=> $_POST['heading'],
					  'body'	=> $_POST['body'],
					  );	
	
	if ($current['heading'] != $revision['heading'] || $current['body'] != $revision['body'])
		$wikiHandler->createRevision($revision);
	
	localRedir("/wiki/" . $wiki['wikiID']);
}
elseif (is_numeric($_GET['revert']) && is_numeric($_GET['wikiID'])) {

	$wiki = $wikiHandler->getByID($_GET['wikiID']);

	// check permission
	if ($groupHandler->getPermission($wiki['categoryID']) < WRITE_ACCESS)
		error(19);	
		
	$wikiHandler->revert($wiki['wikiID'], $_GET['revert']);
	
	localRedir('/wiki/' . $wiki['wikiID']);
}
elseif (is_numeric($_GET['delete'])) {
	$wiki = $wikiHandler->getByID($_GET['delete']);

	// check permission
	if ($groupHandler->getPermission($wiki['categoryID']) < WRITE_ACCESS)
		error(19);	
		
	$wikiHandler->delete($wiki['wikiID']);
	
	localRedir('/homepage/' . $wiki['categoryID']);
	
} else
	error(2);

?>