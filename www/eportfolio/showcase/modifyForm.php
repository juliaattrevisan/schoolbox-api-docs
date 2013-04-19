<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Eportfolio;

require_once dirname(__FILE__) . '/../../../include/Bootstrap.php';

$page = new \SitePage();

$action = \Scrub::text($_POST['action']);
$ownerId = \Scrub::text($_POST['userId']);
$pageId = 'showcase';

$authenticator = new \Eportfolio_Authenticator($_SESSION['id'], $ownerId, $pageId);

if ($authenticator->canModify()) {
	if ($action == 'unpublish') {
		$showcaseItemId = \Scrub::text($_POST['showcaseItemId']);

		$showcasedItem = ShowcaseMapper::getInstance()->findById($showcaseItemId);

		ShowcaseMapper::getInstance()->delete($showcasedItem);
	}
	else if ($action == 'publish') {
		$boxId = \Scrub::text($_POST['boxId']);

		$showcasedItem = new Showcase();
		$showcasedItem->setSubmissionBoxId($boxId);
		$showcasedItem->setUid($ownerId);

		ShowcaseMapper::getInstance()->create($showcasedItem);
	}

	\SitePage::localRedir("/eportfolio/{$ownerId}/showcase");
}
