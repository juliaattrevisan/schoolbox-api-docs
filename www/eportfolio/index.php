<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
$userId = $this->arg[0];
$pageId = $this->arg[1];

if (is_numeric($pageId)) {
	$eportfolioPage = new Eportfolio_Page_Instance($this, $userId, $pageId);
	$eportfolioPage->render();
}
else if ($pageId == 'showcase') {
	$eportfolioPage = new Eportfolio_Page_Showcase($this, $userId, $pageId);
	$eportfolioPage->render();
}
else if ($pageId == 'create') {
	$eportfolioPage = new Eportfolio_Page_Create($this, $userId, $pageId);
	$eportfolioPage->render();
}
else {
	$eportfolioPage = new Eportfolio_Page_Profile($this, $userId, $pageId);
	$eportfolioPage->render();
}

