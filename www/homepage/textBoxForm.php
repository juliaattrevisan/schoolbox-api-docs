<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/homepage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/textBox.php';

$page = new SitePage;
$groupHandler = new Group;
$textBoxHandler = new TextBox; 

if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS)
	error(19);

if (isset($_POST['textBoxHeading']) && is_numeric($_POST['categoryID'])) {

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
	$homepageHandler->updateComponentConfig(36, $config);
	
	localRedir('/homepage/' . $_POST['categoryID']);
	
}

?>