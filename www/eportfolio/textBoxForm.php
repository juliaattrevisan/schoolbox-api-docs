<?php
define('EPORTFOLIO_COMPONENT_TEXTBOX' , 2);

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio-textBox.php';

$page = new SitePage;
$groupHandler = new Group;
$eportfolioHandler = new ePortfolio($_POST['userID']);
$textBoxHandler = new ePortfolioTextBox;

if (isset($_POST['textBoxHeading']) && is_numeric($_POST['userID'])) {

	if (!$eportfolioHandler->canEdit($_POST['userID'])) {
		error(19);
    }

	$textBox = array(
        'userID'	=> $_POST['userID'],
        'heading'	=> $_POST['textBoxHeading'],
        'body'		=> $_POST['textBoxBody'],
    );
	
	if (is_numeric($_POST['textBoxID'])) {
		$textBox['textBoxID'] = $_POST['textBoxID'];
		$textBoxHandler->update($textBox);
	}
    else {
		$textBox['textBoxID'] = $textBoxHandler->create($textBox);
	}
	
	/* select this textBox
     */
	$eportfolioHandler = new ePortfolio($_POST['userID']);
	$config = serialize(array('textBoxID' => $textBox['textBoxID']));
	$eportfolioHandler->updateComponentConfig(EPORTFOLIO_COMPONENT_TEXTBOX, $config);
	
	localRedir('/eportfolio/' . $_POST['userID']);
}
else if (is_numeric($_GET['delete'])) {
	$textBox = $textBoxHandler->getByID($_GET['delete']);

	if (!is_array($textBox)) {
		error(2);
    }

	if ($eportfolioHandler->canEdit($_POST['userID'])) {
		error(8);
    }

	$textBoxHandler->delete($_GET['delete']);
	
	$current = $textBoxHandler->getCurrent($textBox['userID']);
	$eportfolioHandler = new ePortfolio($textBox['userID']);
	$config = serialize(array('textBoxID' => $current['textBoxID']));
	$eportfolioHandler->updateComponentConfig(EPORTFOLIO_COMPONENT_TEXTBOX, $config);

	localRedir('/eportfolio/' . $textBox['userID']);
}

/* Redirect back to ePortfolio if nothing happened
 */
localRedir('/eportfolio/' . $_SESSION['id']);
?>