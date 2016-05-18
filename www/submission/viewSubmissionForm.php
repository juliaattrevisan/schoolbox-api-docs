<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$submissionHandler = new Submission();
$submissionStudent = new SubmissionStudent();
$subReturn = new SubmissionReturn;


if (is_numeric($_GET['delete'])) {
	$studentSub = $submissionStudent->getByID($_GET['delete']);

	if (!isset($studentSub['id'])) {
		error(44);
	}

	$subBox = $submissionHandler->getByID($studentSub['box_id']);

	if (!$subReturn->securityCheck($studentSub['box_id'])) {
		error(25);
	}

	$submissionStudent->delete($studentSub['id']);

	SitePage::localRedir("/submission/inlineGrading/view/{$studentSub['box_id']}/{$studentSub['owner']}");
}

