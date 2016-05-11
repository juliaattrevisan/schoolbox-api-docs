<?php
/**
 * Delete
 *
 * Softdeletes a Questionbank Category
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
require_once('../library/Bootstrap.php');

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
