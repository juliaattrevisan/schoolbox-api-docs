<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionStudent.php';

$page = new SitePage;
$submissionHandler = new Submission;
$submissionStudent = new SubmissionStudent;

if (is_numeric($_GET['view'])) {

	$studentSub = $submissionStudent->getByID($_GET['view']);
	
	if (!isset($studentSub['id']))
		error(44);
	
	$subBox = $submissionHandler->getByID($studentSub['box_id']);
	
	if ($subBox['owner'] != $_SESSION['id'])
		error(44);


	if($_GET['save'])
		$attach = "attachment;";
	else
		$attach = "";

	//all checks completed.. continue with file send
	$filename = SUBMISSION_directory . "/" . $studentSub['id'] . ".dat";

	//send headers
	header ("Content-Type: application/octet-stream");
	header ("Content-Disposition: $attach filename=\"".$studentSub['lastname']."-".$studentSub['realname']."\"");
	header ("Content-Length: ".filesize($filename));
	readfile($filename);
}
elseif (is_numeric($_GET['delete'])) {

	$studentSub = $submissionStudent->getByID($_GET['delete']);

	if (!isset($studentSub['id']))
		error(44);

	$subBox = $submissionHandler->getByID($studentSub['box_id']);
	
	if ($subBox['owner'] != $_SESSION['id'])
		error(44);

	
	$submissionStudent->delete($studentSub['id']);
	
}

?>