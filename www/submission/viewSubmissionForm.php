<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionStudent.php';

$page = new SitePage;
$submissionHandler = new Submission;
$submissionStudent = new SubmissionStudent;

if (is_numeric($_GET['delete'])) {

	$studentSub = $submissionStudent->getByID($_GET['delete']);

	if (!isset($studentSub['id']))
		error(44);

	$subBox = $submissionHandler->getByID($studentSub['box_id']);
	
	if ($subBox['owner'] != $_SESSION['id'])
		error(44);

	
	$submissionStudent->delete($studentSub['id']);

	localRedir('/submission/viewSubmission/'.$studentSub['box_id']);
	
}

?>