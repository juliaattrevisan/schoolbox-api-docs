<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once dirname(__FILE__) . '/../../../../include/Bootstrap.php';

$page = new SitePage();

$action = $_REQUEST['action'];
$ownerId = $_REQUEST['userId'];
$pageId = $_REQUEST['pageId'];
$componentInstanceId = $_REQUEST['componentInstanceId'];

$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $pageId);

if ($authenticator->canModify()) {

	/* Add a list item
	 */
	if ($action == 'addItem' && $_POST['title']) {
		$title = $_POST['title'];
		$content = $_POST['content'];

		$listItem = new \Component\Eportfolio\AdvancedList();
		$listItem->setComponentInstanceId($componentInstanceId);
		$listItem->setTitle($title);
		$listItem->setContent($content);
		$listItem->setSequence(1);

		$uploadedFilename = $_FILES['imagehash']['name'];
		$uploadedLocation = $_FILES['imagehash']['tmp_name'];
		$uploadedUid = $_SESSION['id'];

		if (is_readable($uploadedLocation)) {
			$allowedFiles = new \Schoolbox\File\MimetypeMapper();
			$mime = $allowedFiles->getByFilename($_FILES['imagehash']['name']);

			if(is_object($mime) && $mime->getEnable() == 'true' && stristr($mime->getMime(),'image/')) {
				$imageResource = Schoolbox\FileMapper::getInstance()->upload($uploadedFilename, $uploadedLocation, $uploadedUid);
				$listItem->setImagehash($imageResource->getHash());
			}
		}

		\Component\Eportfolio\AdvancedListMapper::getInstance()->save($listItem);
	}
	/* Update a list item
	 */
	else if ($action == 'updateItem' && $_POST['title']) {
		$listItemId = $_POST['listItemId'];
		$title = $_POST['title'];
		$content = $_POST['content'];

		$listItem = \Component\Eportfolio\AdvancedListMapper::getInstance()->findById($listItemId);
		$listItem->setTitle($title);
		$listItem->setContent($content);

		$uploadedFilename = $_FILES['imagehash']['name'];
		$uploadedLocation = $_FILES['imagehash']['tmp_name'];
		$uploadedUid = $_SESSION['id'];

		if (is_readable($uploadedLocation)) {
			$allowedFiles = new \Schoolbox\File\MimetypeMapper();
			$mime = $allowedFiles->getByFilename($_FILES['imagehash']['name']);

			if(is_object($mime) && $mime->getEnable() == 'true' && stristr($mime->getMime(),'image/')) {
				$imageResource = Schoolbox\FileMapper::getInstance()->upload($uploadedFilename, $uploadedLocation, $uploadedUid);
				$listItem->setImagehash($imageResource->getHash());
			}
		}

		\Component\Eportfolio\AdvancedListMapper::getInstance()->save($listItem);
	}
	/* Delete a list item
	 */
	else if ($action == 'removeItem') {
		$listItemId = $_GET['listItemId'];

		$listItem = Component\Eportfolio\AdvancedListMapper::getInstance()->findById($listItemId);
		Component\Eportfolio\AdvancedListMapper::getInstance()->delete($listItem);
	}

	SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}