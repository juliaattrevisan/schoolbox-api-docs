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
$showcaseItemId = \Scrub::text($_POST['showcaseItemId']);

$authenticator = new \Eportfolio_Authenticator($_SESSION['id'], $ownerId, $pageId);

if ($authenticator->canModify()) {
	if ($action == 'unpublish') {
		$test = ShowcaseMapper::getInstance()->findById($showcaseItemId);

		ShowcaseMapper::getInstance()->delete($test);
	}

	\SitePage::localRedir("/eportfolio/{$ownerId}/showcase");
}