<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/homepage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/textBox.php';

$page = new SitePage;
$groupHandler = new Group;
$textBoxHandler = new TextBox;

if (isset($_POST['textBoxHeading']) && is_numeric($_POST['categoryID'])) {

	if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS)
		error(19);

	$textBox = array('categoryID'	=> $_POST['categoryID'],
					 'heading'		=> $_POST['textBoxHeading'],
					 'body'			=> $_POST['textBoxBody'],
					 );
	
	if (is_numeric($_POST['textBoxID'])) {
		$textBox['textBoxID'] = $_POST['textBoxID'];
		$textBoxHandler->update($textBox);
	} else {
		$textBox['textBoxID'] = $textBoxHandler->create($textBox);
	}
	
	/* select this textBox */
	$homepageHandler = new Homepage($_POST['categoryID']);
	$config = serialize(array('textBoxID' => $textBox['textBoxID']));
	$homepageHandler->updateComponentConfig($_POST['componentID'], $config);
	
	localRedir('/homepage/' . $_POST['categoryID']);
	
}
elseif (is_numeric($_GET['delete'])) {
	$textBox = $textBoxHandler->getByID($_GET['delete']);

	if(!is_array($textBox))
		error(2);

	if ($groupHandler->getPermission($textBox['categoryID']) < WRITE_ACCESS)
		error(8);

	$textBoxHandler->delete($_GET['delete']);
	
	$current = $textBoxHandler->getCurrent($textBox['categoryID']);
	$homepageHandler = new Homepage($textBox['categoryID']);
	$config = serialize(array('textBoxID' => $current['textBoxID']));
	$homepageHandler->updateComponentConfig($_POST['componentID'], $config);

	localRedir('/homepage/' . $textBox['categoryID']);
		
}

?>