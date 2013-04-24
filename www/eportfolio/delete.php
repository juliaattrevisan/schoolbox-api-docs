<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
$currentUserId = $_SESSION['id'];
$ownerId = $this->arg[0];
$pageId = $this->arg[1];

$authenticator = new Eportfolio_Authenticator($currentUserId, $ownerId, $pageId);

if ($authenticator->canModify()) {
	if (is_numeric($pageId)) {
		$eportfolioHandler = new Eportfolio($currentUserId, $pageId);
		$eportfolioHandler->delete();
	}
	else {

		/* You cannot delete Profile or Showcase pages :>
		 */
		error(19);
	}
}
else {
	error(19);
}


