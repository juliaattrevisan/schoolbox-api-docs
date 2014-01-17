<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

/* Start a session -_-
 */
$page = new SitePage();

/* Get the owner object
 */
$userMapper = new \Schoolbox\UserMapper();
$user = $userMapper->findById($_POST['uid']);

/* Get the submission box object
 */
$submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
$submissionBox = $submissionBoxMapper->findById($_POST['box_id']);

/* Some weird format that's built in javascript for some reason
 * See jquery.viewSubmission.js
 */
$mark = $_POST['mark'][$_POST['uid']];

/* Optional rubric
 */
$rubric = $_POST['rubric'];

/* The usual comment
 */
$comment = $_POST['comment'];

/* Optional file upload
 */
$file = $_FILES['file'];

/* A url to visit after submitting
 */
$redirect = $_POST['redirect'];

/* Process the return's comments, marks, rubrics and uploads
 */
$returnedWorkFactory = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\Factory($user, $submissionBox, $mark, $comment, $rubric, $file, $redirect);
$returnedWorkFactory->start();

/* An array of crocodoc-specific controls
 array(
	'isCrocodoc' => true
	'submissionId' => ...
	'nextUrl' => ...
 );

 */
/* Deal with crocodoc specifically
 */
if (isset($_POST['crocodoc'])) {
	try {
		$submissionMapper = new \Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();
		$submission = $submissionMapper->findById($_POST['crocodoc']['submissionId']);

		$downloader = new Crocodoc\Download($submission);
		$downloader->start();
	}
	catch(\Exception $e) {
		new \Alaress_Probe($_POST);
		echo $e->getMessage();
	}
}

/* Go to St. Albans when finished
 */
$page->localRedir($redirect);