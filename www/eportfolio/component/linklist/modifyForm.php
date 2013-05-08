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
	if ($action == 'addItem') {
		$title = $_POST['title'];
		$content = $_POST['content'];

		/* Check if it's a valid url before putting in db
		 */
		$url = new Url($content);
		$url->prependScheme();

		if ($url->isUrl()) {
			$listItem = new \Component\Eportfolio\LinkList();
			$listItem->setComponentInstanceId($componentInstanceId);
			$listItem->setTitle($title);
			$listItem->setContent($url->getUrl());
			$listItem->setSequence(1);

			\Component\Eportfolio\LinkListMapper::getInstance()->save($listItem);
		}
	}
	/* Update a list item
	 */
	else if ($action == 'updateItem') {
		$listItemId = $_POST['listItemId'];
		$title = $_POST['title'];
		$content = $_POST['content'];

		/* Check if it's a valid url before putting in db
		 */
		$url = new Url($content);
		$url->prependScheme();

		if ($url->isUrl()) {
			$listItem = \Component\Eportfolio\LinkListMapper::getInstance()->findById($listItemId);
			$listItem->setTitle($title);
			$listItem->setContent($url->getUrl());

			\Component\Eportfolio\LinkListMapper::getInstance()->save($listItem);
		}
	}
	/* Delete a list item
	 */
	else if ($action == 'removeItem') {
		$listItemId = $_GET['listItemId'];

		$listItem = \Component\Eportfolio\LinkListMapper::getInstance()->findById($listItemId);
		\Component\Eportfolio\LinkListMapper::getInstance()->delete($listItem);
	}

	SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}