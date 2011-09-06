<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$submissionHandler = new Submission();
$submissionStudent = new SubmissionStudent();

if (is_numeric($_GET['delete'])) {
	$studentSub = $submissionStudent->getByID($_GET['delete']);

	if (!isset($studentSub['id'])) {
		error(44);
	}

	$subBox = $submissionHandler->getByID($studentSub['box_id']);

	if ($subBox['owner'] != $_SESSION['id']) {
		error(44);
	}

	$submissionStudent->delete($studentSub['id']);

	SitePage::localRedir('/submission/viewSubmission/' . $studentSub['box_id']);
}

