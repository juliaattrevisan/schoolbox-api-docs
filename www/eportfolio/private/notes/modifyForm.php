<?php
require_once(dirname(__FILE__) . '/../../../../include/Bootstrap.php');
session_start();

/* Create/update
 */
if (isset($_POST['privateNotes'])) {
	$privateNotes = new Eportfolio_Private_Notes(null, Scrub::text($_POST['uid']));
	$privateNotes->setUserId(Scrub::text($_POST['uid']));
	$privateNotes->update($_POST);
}
else {
	error(2);
}

