<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio-private-notes.php';

$page = new SitePage();
$ePortfolioPrivateNotesHandler = new ePortfolioPrivateNotes();

$privateNotes = $ePortfolioPrivateNotesHandler->getByID($_POST['uid']);

$privateNotes = array(
	'uid' => $_POST['uid'],
	'privateNotes' => Scrub::html($_POST['privateNotes'])
);

if ($ePortfolioPrivateNotesHandler->getByID($_POST['uid'])) {
	$ePortfolioPrivateNotesHandler->update($privateNotes);
}
else {
	$ePortfolioPrivateNotesHandler->create($privateNotes);
}

SitePage::localRedir('/eportfolio/' . Scrub::text($_POST['uid']));