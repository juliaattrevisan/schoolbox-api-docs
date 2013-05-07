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
$listItemId = $_REQUEST['listItemId'];
$title = $_REQUEST['title'];
$content = $_REQUEST['content'];

$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $pageId);

if ($authenticator->canModify()) {

	/* Add a list item
	 */
	if ($action == 'addItem') {
		$now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

		$listItem = new \Component\Eportfolio\Blog();
		$listItem->setPageId($pageId);
		$listItem->setUid($ownerId);
		$listItem->setTitle($title);
		$listItem->setContent($content);
		$listItem->setCreatedAt($now->getDatabase());
		$listItem->setUpdatedAt($now->getDatabase());

		\Component\Eportfolio\BlogMapper::getInstance()->save($listItem);
	}
	/* Update a list item
	 */
	else if ($action == 'updateItem') {
		$now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

		$listItem = \Component\Eportfolio\BlogMapper::getInstance()->findById($listItemId);
		$listItem->setTitle($title);
		$listItem->setContent($content);
		$listItem->setUpdatedAt($now->getDatabase());

		\Component\Eportfolio\BlogMapper::getInstance()->save($listItem);
	}
	/* Delete a list item
	 */
	else if ($action == 'removeItem') {
		$listItem = \Component\Eportfolio\BlogMapper::getInstance()->findById($listItemId);
		\Component\Eportfolio\BlogMapper::getInstance()->delete($listItem);
	}

	SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}