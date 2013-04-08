<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

/* -_-
 */
$page = new SitePage();

new Alaress_Probe($_POST);

/* Check create/update permissions
 */

/* Update page
 */
if (isset($_POST['page_id']) && is_numeric($_POST['page_id'])) {
}
/* Create page
 */
else if (isset($_POST['page_title']) && strlen(trim($_POST['page_title'])) && isset($_POST['page_template']) && is_numeric($_POST['page_template'])) {

	/* Create page for the currently logged-in user
	 */
	$userId = $_SESSION['id'];
	$pageId = null;
	$pageTitle = Scrub::text($_POST['page_title']);
	$templateId = Scrub::text($_POST['page_template']);

	/* Create page and apply components (a page_id is generated)
	 */
	$eportfolioHandler = new Eportfolio($userId, $pageId);
	$eportfolioHandler->createEportfolioFromTemplate($pageTitle, $templateId);

	/* Apply permissions to the newly-created page
	 */
	Eportfolio_PermissionMapper::getInstance()->updatePermissions($eportfolioHandler->getPageId(), $_POST['parent_permission'], $_POST['group_permission'], $_POST['individual_permission']);

	/* Add new permissions
	 */

	/* Redirect to newly created page
	 */
}
else {
	error(2);
}

