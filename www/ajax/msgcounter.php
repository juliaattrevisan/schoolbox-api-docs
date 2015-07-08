<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// disallow sitepage context
if(isset($this)) {
	/** @var SitePage $this */
	$this->localRedir('/');
	exit;
}

header('Content-Type: application/json');

// make sure we've got access to the session
// (No bootstrap is okay, $_SESSION['user'] is not used)
session_start();

// only 1 var, must be 'c', must be a number >= 0
$valid =
	isset($_POST['c']) &&
	count($_POST) == 1 &&
	is_numeric($_POST['c']) &&
	$_POST['c'] >= 0;


if(!$valid) {
	header(':', true, '400');
	die('');
}

$_SESSION['msg-count'] = $_POST['c'];
header(':', true, 202);
die('');