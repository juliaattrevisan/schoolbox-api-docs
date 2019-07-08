<?php
/**
 * Delete
 *
 * Softdeletes a Questionbank Category
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link		https://schoolbox.com.au/
 */
require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(126);
}

$categoryHandler = new Survey_Question_Category();

if (is_numeric($this->arg[0])) {
	$categoryHandler->delete($categoryHandler->getByID($this->arg[0]));
}

/* Back to the future -_-
 */
$page = new SitePage();
$page->backToTheFuture(1);
