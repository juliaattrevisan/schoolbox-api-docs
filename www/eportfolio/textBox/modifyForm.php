<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio-textBox.php';

$page = new SitePage();
$groupHandler = new Group();
$eportfolioHandler = new ePortfolio($_SESSION['id']);
$textBoxHandler = new ePortfolioTextBox();

if (isset($_POST['heading']) && isset($_POST['body'])) {
	if (!$eportfolioHandler->canEdit(scrubTEXT($_POST['userID']))) {
		error(19);
    }

	$textBox = array(
		'userID' => scrubTEXT($_POST['userID']),
		'componentLocationID' => scrubTEXT($_POST['componentLocationID']),
        'heading' => scrubHTML($_POST['heading']),
        'body' => scrubHTML($_POST['body']),
    );

	/* If an ID was provided, update existing record; otherwise create
	 */
	if (is_numeric($_POST['textBoxID'])) {
		$textBox['textBoxID'] = scrubTEXT($_POST['textBoxID']);

		$textBoxHandler->update($textBox);
	}
    else {
		$textBoxHandler->create($textBox);
	}

	localRedir('/eportfolio/' . $_SESSION['id']);
}
else if (is_numeric($_POST['delete'])) {
	$textBoxHandler->delete(scrubTEXT($_POST['delete']));
}
else {
	error(2);
}