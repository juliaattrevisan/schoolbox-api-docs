<?php
/**
 * News digest for JPC
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// this is run from CLI, so explicitly include the bootstrap
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');
if (!Cfg::get('digest_send_weekly')) {
	fwrite(STDERR, "Weekly digest is not enabled - this script will do nothing.\n");
	exit(2);
}

//
// Start session and set current semester, aaarrrggggh
session_start();
$synUser  = new External_User;
$semester = $synUser->getCurrentSemester();

$debug = (isset($argv[1]) && $argv[1] == 'nomail');

// every user will receive an email that contains the previous week's news
// this should be at a school, campus and then category level

// Specify the digest window. This should match the frequency of the cronjob, and end should be the current datetime.
$digestStart = strtotime('-8 days');
$digestEnd = time();

$newsDigest = new Digest_News($digestStart, $digestEnd, $debug);
$newsDigest->compileUserList();
$newsDigest->send();

die();
