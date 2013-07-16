<?php

// require no sitepage context
if(isset($this)) {
    // vicious lies
    $this->pageNotFound();
}

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$groupHandler = new Group();
$textBoxHandler = new TextBox();

if (isset($_POST['textBoxHeading']) && is_numeric($_POST['categoryID'])) {

	if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS) {
		error(19);
	}

	$textBox = array(
		'categoryID' => $_POST['categoryID'],
		'heading' => $_POST['textBoxHeading'],
		'body' => $_POST['textBoxBody'],
	);

	if (is_numeric($_POST['textBoxID'])) {
		$textBox['textBoxID'] = $_POST['textBoxID'];
		$textBoxHandler->update($textBox);
	}
	else {
		$textBox['textBoxID'] = $textBoxHandler->create($textBox);
	}

	// select this textBox
    $cim = Component_InstanceMapper::getInstance();
    $instances = array_values($cim->findByInstanceId($_POST['componentID']));
    /* @var $instance Component_Instance */
    $instance  = $instances[0];

    $ccm = Component_ConfigMapper::getInstance();
    $config = new Component_Config();
    $config
        ->setInstanceId($instance->getId())
        ->setConfigDefinitionId($instance->getConfig()->first()->getId())
        ->setValue($textBox['textBoxID']);
    $ccm->save($config);

    SitePage::localRedir('/homepage/' . $_POST['categoryID']);
}
else if (is_numeric($_GET['delete'])) {
	$textBox = $textBoxHandler->getByID($_GET['delete']);

	if (!is_array($textBox)) {
		error(2);
	}

	if ($groupHandler->getPermission($textBox['categoryID']) < WRITE_ACCESS) {
		error(8);
	}

	$textBoxHandler->delete($_GET['delete']);

	SitePage::localRedir('/homepage/' . $textBox['categoryID']);
}
