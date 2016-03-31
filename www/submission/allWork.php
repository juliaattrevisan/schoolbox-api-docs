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
use Schoolbox\Assessment\Term;

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

// Is a term set? Use it if so, otherwise use most recent
$termMapper = Term\Mapper::getInstance();
if (isset($this->arg[1])) {
	try {
		$term = $termMapper->findOneByColumn('code', $this->arg[1]);
	} catch (\Schoolbox\Mapper\Exception $e) {
		$this->localRedir('/submission/allWork/'.$userId);
		exit;
	}
} else {
	$term = $termMapper->findForToday();
}

$subject = isset($this->arg[2]) ? $this->arg[2] : false;

/* DEFINE HANDLERS - TODO DELETE THIS SECTION */
$groupHandler = new Group();
$boxHandler = new Submission();

/* PERMISSIONS CHECKS GO HERE */
$permission = PERMISSION_DENIED;

// don't run this check if there is no subject selected
if($subject) {
	// check student and subject combination matches
	$subjectArray = $groupHandler->involvedInCategoryList($userId);
	$subjectIdArray = array();
	foreach($subjectArray as $s) {
		$subjectIdArray[] = $s['id'];
	}

	// if the student is enrolled, access is granted
	if(in_array($subject, $subjectIdArray)) {
		$permission = PERMISSION_GRANTED;
	}
	// if the student is not enrolled, check if they have submitted to the subject
	else {
		$catArray = $boxHandler->submittedToCategoryList(
			$userId,
			$term->getStart(),
			$term->getActualFinish()
		);
		$catIdArray = array();
		foreach($catArray as $c) {
			$catIdArray[] = $c['id'];
		}

		if(in_array($subject, $catIdArray)) {
			$permission = PERMISSION_GRANTED;
		}
	}

	if($permission == PERMISSION_DENIED) {
		error(19);
	}
}

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

// P3: if you are a parent, you may only view this if the ID matches one of your children, also make sure config allows parents to view
elseif($groupHandler->isParentOfChild($userId) && !Config::get('restrict_parent_grades')) {
	$permission = PERMISSION_GRANTED;
}

if($permission == PERMISSION_DENIED) {
	error(19);
}
/* /PERMISSIONS */

// ok. from here we're actually going to show the page, and not redirect
$report = new StudentReportCard($userId);
$report->setTerm($term);

if($subject) {
	$report->setSubjectWithinTerm($subject);
}
else {
	$this->setVar(array(
		'GRAPH_IS_DISPLAYED' => 0,
		'GRAPH_JSON' => '{}',
		'GRAPH_COLOURS' => '{}',
		'GRAPH_XMIN' => '0',
		'GRAPH_XMAX' => '0'
	));
}

/* At this point you don't HAVE to render() - if you want it in a different format,
 * the class is structured to accommodate that
 * */
$report->renderPage($this);
