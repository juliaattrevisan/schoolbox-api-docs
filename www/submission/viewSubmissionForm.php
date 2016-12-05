<?php

/*
 *
 * redirect to Redirection to 16.5 url
 *
 */
SitePage::localRedir('/', null, 410);

/*
*Old code
*/
require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');

$page = new SitePage();
$submissionHandler = new Submission();
$submissionStudent = new SubmissionStudent();
$submissionFileMapper = \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper::getInstance();
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

	if (!$box->hasAccess($sessionUser, \Schoolbox\Permission\Access\UserAccess::ACCESS_WRITE)) {
		error(25);
		exit();
	}

    $submissionFileMapper->deleteByColumnList([
        'student_submission_id' => $studentSub['id'],
        'hash' => $_GET['hash']
    ]);

	SitePage::localRedir("/submission/inlineGrading/view/{$studentSub['box_id']}/{$studentSub['owner']}");
}

