<?php
/**
 * Move
 *
 * Moves a question around in the Questionbank Category tree, reassigning
 * parent_ids where appropriate based on $_GET['position']
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link		https://schoolbox.com.au/
 */
require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$groupHandler = new Group();

if ($groupHandler->isStaff() || $groupHandler->isSuperuser()) {
	$categoryHandler = new Survey_Question_Category();
	$categoryHandler->move($_GET['target'], $_GET['destination'], $_GET['position']);
}
else {
	error(126);
}
