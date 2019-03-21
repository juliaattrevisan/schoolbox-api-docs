<?php
/**
 * Move
 *
 * Moves a question around in the Questionbank Category tree, reassigning
 * parent_ids where appropriate based on $_GET['position']
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();
$page = new SitePage();
$groupHandler = new Group();

if ($groupHandler->isStaff() || $groupHandler->isSuperuser()) {
	$categoryHandler = new Survey_Question_Category();
	$categoryHandler->move($_GET['target'], $_GET['destination'], $_GET['position']);
}
else {
	error(126);
}
