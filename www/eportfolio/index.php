<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
$currentUserId = $_SESSION['id'];
$ownerId = $this->arg[0];
$pageId = $this->arg[1];

$authenticator = new Eportfolio_Authenticator($currentUserId, $ownerId, $pageId);

if ($authenticator->pageExists($ownerId, $pageId)) {
	if ($authenticator->canCreate() || $authenticator->canModify()) {
		$factory = new Eportfolio_Page_Factory($this, $ownerId, $pageId, FULL_ACCESS);
		$factory->getPage()->renderPage();
	}
	else if ($authenticator->canView()) {
		$factory = new Eportfolio_Page_Factory($this, $ownerId, $pageId, READ_ACCESS);
		$factory->getPage()->renderPage();
	}
	else {
		error(19);
	}
}
else {
	error(156);
}



