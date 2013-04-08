<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
$userId = $this->arg[0];
$pageId = $this->arg[1];

$authenticator = new Eportfolio_Authenticator($userId, $pageId);

if ($authenticator->canView()) {
	$factory = new Eportfolio_Page_Factory($this, $userId, $pageId);
	$factory->getPage()->renderPage();
}
else {
	error(19);
}


