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
	if ($action == 'addItem' && $_POST['content']) {
		$content = $_POST['content'];

		$listItem = new Component_Eportfolio_BasicList();
		$listItem->setComponentInstanceId($componentInstanceId);
		$listItem->setContent($content);
		$listItem->setSequence(1);

		Component_Eportfolio_BasicListMapper::getInstance()->save($listItem);
	}
	/* Update a list item
	 */
	else if ($action == 'updateItem') {
		$listItemId = $_POST['listItemId'];
		$content = $_POST['content'];

		$listItem = Component_Eportfolio_BasicListMapper::getInstance()->findById($listItemId);
		$listItem->setContent($content);

		Component_Eportfolio_BasicListMapper::getInstance()->save($listItem);
	}
	/* Delete a list item
	 */
	else if ($action == 'removeItem') {
		$listItemId = $_GET['listItemId'];

		$listItem = Component_Eportfolio_BasicListMapper::getInstance()->findById($listItemId);
		Component_Eportfolio_BasicListMapper::getInstance()->delete($listItem);
	}

	SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}