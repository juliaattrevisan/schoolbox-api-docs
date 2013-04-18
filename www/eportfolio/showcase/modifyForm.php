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

$action = Scrub::text($_POST['action']);
$ownerId = Scrub::text($_POST['userId']);
$pageId = 'showcase';

$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $pageId);

if ($authenticator->canModify()) {
	echo "Mort";
}