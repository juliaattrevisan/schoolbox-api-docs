<?php
/**
 *
 * This URI could alternatively be something like "/submission/report"
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

define('PERMISSION_GRANTED', true);
define('PERMISSION_DENIED', false);

use Schoolbox\Assessment\StudentReportCard;

// require sitepage context
if(!isset($this)) {
	require_once '../../include/Bootstrap.php';
	SitePage::localRedir('/submission/allWork');
}

$userId = $_SESSION['id'];

if(isset($this->arg[0])) {
	if(is_numeric($this->arg[0])) {
		$userId = $this->arg[0];
	}
	else {
		// this is a fail case
		$this->localRedir("/submission/allWork/");
	}
}

// redirect if GET params are specified; make the URL look pretty
if(isset($_GET['year']) || isset($_GET['semester'])) {
	$this->localRedir("/submission/allWork/$userId/{$_GET['year']}/{$_GET['semester']}");
}

$year = isset($this->arg[1]) ? $this->arg[1] : date('Y');
if(isset($this->arg[2])) {
	$semester = $this->arg[2];
	if($semester > 2) {
		error(105, 'Semester can only be 1 or 2.');
	}
}
else {
	$semester = date('M') > 6 ? 2 : 1;
}

$subject = isset($this->arg[3]) ? $this->arg[3] : false;

/* DEFINE HANDLERS - TODO DELETE THIS SECTION */
$groupHandler = new Group();

/* PERMISSIONS CHECKS GO HERE */
$permission = PERMISSION_DENIED;

// P0: if you're viewing your own, permission granted
if($userId == $_SESSION['id']) {
	$permission = PERMISSION_GRANTED;
}

// P1: if you are a super, permission granted
elseif(isset($_SESSION['su'])) {
	$permission = PERMISSION_GRANTED;
}

// P2: if you are a teacher, permission granted
elseif($groupHandler->isStaff()) {
	$permission = PERMISSION_GRANTED;
}

// P3: if you are a parent, you may only view this if the ID matches one of your children
elseif($groupHandler->isParentOfChild($userId)) {
	$permission = PERMISSION_GRANTED;
}

if($permission == PERMISSION_DENIED) {
	error(19);
}
/* /PERMISSIONS */


// ok. from here we're actually going to show the page, and not redirect
$report = new StudentReportCard($userId);
$report->setSemester($year, $semester);

if($subject) {
	$report->setSubject($subject);
}

/* At this point you don't HAVE to render() - if you want it in a different format,
 * the class is structured to accommodate that
 * */
$report->renderPage($this);
