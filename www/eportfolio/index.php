<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 * Originally cloned from bugalugs
 */

$userId = $this->arg[0];
$pageId = $this->arg[1];

$eportfolioPage = new Eportfolio_Page_Instance($this, $userId, $pageId);
$eportfolioPage->render();