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

/* All these are accessed via get from /cms/portfolio (My Files)
 */
$action = $_GET['action'];
$ownerId = $_GET['userId'];
$pageId = $_GET['pageId'];
$componentInstanceId = $_GET['componentInstanceId'];

$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $pageId);

if ($authenticator->canModify()) {

	/* Add a list item
	 */
	if ($action == 'addItem') {
		$fileId = $_GET['fileId'];

		/* Prevent dupe publishings
		 */
		$existing = Component_Eportfolio_FileListMapper::getInstance()->findByColumnList(array(
			'component_instance_id' => $componentInstanceId,
			'file_id' => $fileId
		));

		/* Publish it if it isn't already published
		 */
		if (!$existing->count()) {
			$now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

			$listItem = new Component_Eportfolio_FileList();
			$listItem->setComponentInstanceId($componentInstanceId);
			$listItem->setFileId($fileId);
			$listItem->setCreatedAt($now->getDatabase());
			$listItem->setUpdatedAt($now->getDatabase());

			Component_Eportfolio_FileListMapper::getInstance()->save($listItem);
		}
	}
	/* Delete a list item
	 */
	else if ($action == 'removeItem') {
		$listItemId = $_GET['listItemId'];

		$listItem = Component_Eportfolio_FileListMapper::getInstance()->findById($listItemId);
		Component_Eportfolio_FileListMapper::getInstance()->delete($listItem);
	}

	SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}