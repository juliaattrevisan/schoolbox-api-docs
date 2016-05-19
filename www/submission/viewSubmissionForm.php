<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$submissionHandler = new Submission();
$submissionStudent = new SubmissionStudent();
$subReturn = new SubmissionReturn;

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if (is_numeric($_GET['delete'])) {
	$studentSub = $submissionStudent->getByID($_GET['delete']);

	if (!isset($studentSub['id'])) {
		error(44);
	}

	try {
		$submissionBoxMapper = \Schoolbox\Assessment\Component\SubmissionBoxMapper::getInstance();
		$box = $submissionBoxMapper->findById($studentSub['box_id']);
	}
	catch (\Schoolbox\Mapper\Exception $e) {
		error(17);
		exit();
	}

	if (!$submissionBoxMapper->hasAccess($sessionUser, $box, \Schoolbox\Permission\Access\UserAccess::ACCESS_WRITE)) {
		error(25);
		exit();
	}

	$submissionStudent->delete($studentSub['id']);

	SitePage::localRedir("/submission/inlineGrading/view/{$studentSub['box_id']}/{$studentSub['owner']}");
}

