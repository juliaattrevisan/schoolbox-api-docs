<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once dirname(__FILE__) . '/../../../include/Bootstrap.php';

$page = new SitePage();

$action = $_POST['action'];
$ownerId = $_POST['userId'];
$pageId = 'showcase';

$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $pageId);

if ($authenticator->canModify()) {
	if ($action == 'unpublish') {
		$showcaseItemId = $_POST['showcaseItemId'];

		$showcasedItem = \Eportfolio\ShowcaseMapper::getInstance()->findById($showcaseItemId);

		\Eportfolio\ShowcaseMapper::getInstance()->delete($showcasedItem);
	}
	else if ($action == 'publish') {
		$boxId = $_POST['boxId'];
		$now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

		$showcasedItem = new \Eportfolio\Showcase();
		$showcasedItem->setSubmissionBoxId($boxId);
		$showcasedItem->setUid($ownerId);
		$showcasedItem->setCreatedAt($now);
		$showcasedItem->setUpdatedAt($now);

		\Eportfolio\ShowcaseMapper::getInstance()->create($showcasedItem);
	}

	SitePage::localRedir("/eportfolio/{$ownerId}/showcase");
}
