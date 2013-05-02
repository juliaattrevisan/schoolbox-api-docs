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

$action = $_POST['action'];
$ownerId = $_POST['userId'];
$pageId = $_POST['pageId'];
$componentInstanceId = $_POST['componentInstanceId'];

$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $pageId);

if ($authenticator->canModify()) {
	$content = $_POST['content'];

	$textboxCollection = \Component\Eportfolio\TextboxMapper::getInstance()->findByColumn('component_instance_id', $componentInstanceId);

	/* Update existing textbox
	 */
	if ($textboxCollection->count()) {
		$textboxItem = $textboxCollection->current();
		$textboxItem->setContent($content);
		$textboxItem->setSequence(1);

		\Component\Eportfolio\TextboxMapper::getInstance()->save($textboxItem);
	}
	/* Create new one
	 */
	else {
		$textboxItem = new \Component\Eportfolio\Textbox();
		$textboxItem->setComponentInstanceId($componentInstanceId);
		$textboxItem->setContent($content);
		$textboxItem->setSequence(1);

		\Component\Eportfolio\TextboxMapper::getInstance()->save($textboxItem);
	}

	SitePage::localRedir("/eportfolio/{$ownerId}/{$pageId}");
}