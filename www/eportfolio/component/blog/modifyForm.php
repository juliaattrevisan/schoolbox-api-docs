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

$action = Scrub::text($_POST['action']);
$ownerId = Scrub::text($_POST['userId']);
$pageId = Scrub::text($_POST['pageId']);
$listItemId = Scrub::text($_POST['listItemId']);
$title = Scrub::text($_POST['title']);
$content = Scrub::html($_POST['content']);

$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $pageId);

if ($authenticator->canModify()) {

	/* Add a list item
	 */
	if ($action == 'addItem') {
		$now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

		$listItem = new Component_Eportfolio_Blog();
		$listItem->setPageId($pageId);
		$listItem->setUid($ownerId);
		$listItem->setTitle($title);
		$listItem->setContent($content);
		$listItem->setCreatedAt($now->getDatabase());
		$listItem->setUpdatedAt($now->getDatabase());

		Component_Eportfolio_BlogMapper::getInstance()->save($listItem);
	}
	/* Update a list item
	 */
	else if ($action == 'updateItem') {
		$now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

		$listItem = Component_Eportfolio_BlogMapper::getInstance()->findById($listItemId);
		$listItem->setTitle($title);
		$listItem->setContent($content);
		$listItem->setUpdatedAt($now->getDatabase());

		Component_Eportfolio_BlogMapper::getInstance()->save($listItem);
	}
	/* Delete a list item
	 */
	else if ($action == 'removeItem') {
		$listItem = Component_Eportfolio_BlogMapper::getInstance()->findById($listItemId);
		Component_Eportfolio_BlogMapper::getInstance()->delete($listItem);
	}

	SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}