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

		$listItem = new Component_Eportfolio_LinkList();
		$listItem->setComponentInstanceId($componentInstanceId);
		$listItem->setTitle($title);
		$listItem->setContent($content);
		$listItem->setSequence(1);

		Component_Eportfolio_LinkListMapper::getInstance()->save($listItem);
	}
	/* Update a list item
	 */
	else if ($action == 'updateItem') {
		$listItemId = Scrub::text($_GET['listItemId']);
		$title = Scrub::text($_GET['title']);
		$content = Scrub::text($_GET['content']);

		$listItem = Component_Eportfolio_LinkListMapper::getInstance()->findById($listItemId);
		$listItem->setTitle($title);
		$listItem->setContent($content);

		Component_Eportfolio_LinkListMapper::getInstance()->save($listItem);
	}
	/* Delete a list item
	 */
	else if ($action == 'removeItem') {
		$listItemId = Scrub::text($_GET['listItemId']);

		$listItem = Component_Eportfolio_LinkListMapper::getInstance()->findById($listItemId);
		Component_Eportfolio_LinkListMapper::getInstance()->delete($listItem);
	}

	SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}