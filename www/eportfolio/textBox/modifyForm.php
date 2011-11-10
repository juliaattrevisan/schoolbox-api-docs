<?php
require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

$page = new SitePage();
$groupHandler = new Group();
$eportfolioHandler = new Eportfolio_Container($_SESSION['id']);
$textBoxHandler = new Eportfolio_Textbox();

if (isset($_POST['heading']) && isset($_POST['body'])) {
	if (!$eportfolioHandler->canEdit(Scrub::text($_POST['userID']))) {
		error(19);
    }

	$textBox = array(
		'userID' => Scrub::text($_POST['userID']),
		'componentLocationID' => Scrub::text($_POST['componentLocationID']),
        'heading' => Scrub::html($_POST['heading']),
        'body' => Scrub::html($_POST['body']),
    );

	/* If an ID was provided, update existing record; otherwise create
	 */
	if (is_numeric($_POST['textBoxID'])) {
		$textBox['textBoxID'] = Scrub::text($_POST['textBoxID']);

		$textBoxHandler->update($textBox);
	}
    else {
		$textBoxHandler->create($textBox);
	}

	SitePage::localRedir('/eportfolio/' . $_SESSION['id']);
}
else if (is_numeric($_POST['delete'])) {
	$textBoxHandler->delete(Scrub::text($_POST['delete']));
}
else {
	error(2);
}