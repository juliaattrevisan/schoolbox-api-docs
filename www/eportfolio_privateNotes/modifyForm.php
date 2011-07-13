<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio-private-notes.php';

$page = new SitePage;
$eportfolioHandler = new ePortfolio($_POST['uid']);
$eportfolioPrivateNotesHandler = new eportfolioPrivateNotes($_POST['uid']);

$privateNotes = array(
    'uid' => $_POST['uid'],
    'privateNotes' => $_POST['privateNotes']
);
  
$eportfolioPrivateNotesHandler->update($privateNotes);

$page->localRedir('/eportfolio/' . $_POST['uid']);
?>