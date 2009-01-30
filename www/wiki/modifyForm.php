<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/wiki.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';

$page = new SitePage;
$wikiHandler = new Wiki;
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (isset($_POST['heading']) && is_numeric($_POST['categoryID'])) {
	
	// check permission
	if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS)
		error(19);	

	$wiki = array('categoryID'	=> $_POST['categoryID'],
				  'visible'		=> isset($_POST['visible']) ? 'true' : 'false',
				  'closed'		=> isset($_POST['closed']) ? 'true' : 'false',
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

} else
	error(2);

?>