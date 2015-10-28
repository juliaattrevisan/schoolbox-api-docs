<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
$currentUserId = $_SESSION['id'];
$ownerId = is_numeric($this->arg[0]) ? $this->arg[0] : $_SESSION['id'];

$pageId = $this->arg[1]? $this->arg[1]:'profile';

$authenticator = new Eportfolio_Authenticator($currentUserId, $ownerId, $pageId);

if ($authenticator->pageExists()) {
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
	$this->pageNotFound();
}



