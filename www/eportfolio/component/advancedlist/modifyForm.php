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

$action = Scrub::text($_GET['action']);
$ownerId = Scrub::text($_GET['userId']);
$pageId = Scrub::text($_GET['pageId']);
$componentInstanceId = Scrub::text($_GET['componentInstanceId']);

$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $pageId);

if ($authenticator->canModify()) {

	/* Add a list item
	 */
	if ($action == 'addItem') {
		$title = Scrub::text($_GET['title']);
		$content = Scrub::text($_GET['content']);
		$imagehash = Scrub::text($_GET['imagehash']);

		$listItem = new Component_Eportfolio_AdvancedList();
		$listItem->setComponentInstanceId($componentInstanceId);
		$listItem->setTitle($title);
		$listItem->setContent($content);
		$listItem->setImagehash($imagehash);
		$listItem->setSequence(1);

		$uploadedFilename = $_FILES['imagehash']['name'];
		$uploadedLocation = $_FILES['imagehash']['tmp_name'];
		$uploadedUid = $_SESSION['id'];

		if (is_readable($uploadedLocation)) {
			$imageResource = Storage_ResourceMapper::getInstance()->create($uploadedFilename, $uploadedLocation, $uploadedUid);

			$listItem->setImagehash($imageResource->hash);
		}

		Component_Eportfolio_AdvancedListMapper::getInstance()->save($listItem);
	}
	/* Update a list item
	 */
	else if ($action == 'updateItem') {
		$listItemId = Scrub::text($_GET['listItemId']);
		$title = Scrub::text($_GET['title']);
		$content = Scrub::text($_GET['content']);
		$imagehash = Scrub::text($_GET['imagehash']);

		$listItem = Component_Eportfolio_AdvancedListMapper::getInstance()->findById($listItemId);
		$listItem->setTitle($title);
		$listItem->setContent($content);

		$uploadedFilename = $_FILES['imagehash']['name'];
		$uploadedLocation = $_FILES['imagehash']['tmp_name'];
		$uploadedUid = $_SESSION['id'];

		if (is_readable($uploadedLocation)) {
			$imageResource = Storage_ResourceMapper::getInstance()->create($uploadedFilename, $uploadedLocation, $uploadedUid);

			$listItem->setImagehash($imageResource->hash);
		}

		Component_Eportfolio_AdvancedListMapper::getInstance()->save($listItem);
	}
	/* Delete a list item
	 */
	else if ($action == 'removeItem') {
		$listItemId = Scrub::text($_GET['listItemId']);

		$listItem = Component_Eportfolio_AdvancedListMapper::getInstance()->findById($listItemId);
		Component_Eportfolio_AdvancedListMapper::getInstance()->delete($listItem);
	}

	SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}