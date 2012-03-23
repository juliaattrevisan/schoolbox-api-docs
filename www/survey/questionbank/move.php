<?php
/**
 * Modify
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
require_once('../../../include/Bootstrap.php');

$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(126);
}

$categoryHandler = new Survey_Question_Category();
$categoryHandler->move($_GET['target'], $_GET['destination'], $_GET['position']);

