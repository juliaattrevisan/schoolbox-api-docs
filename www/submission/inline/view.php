<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$this->setFile(TEMPLATE_content, 'submission/inline/view.html');

$submissionMapper = new \Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();
$submission = $submissionMapper->findById($this->arg[0]);
$submissionBoxId = $submission->getSubmissionBox()->getId();

$submissionHandler = new Submission();
$submissionBox = $submissionHandler->getById($submissionBoxId);

$studentList = $submissionHandler->getAllStudentsForSubmission($submissionBox);

/* Trim out only this student
 */
$singleStudent = array();

foreach($studentList as $currentStudent) {
	if ($currentStudent['uid'] == $submission->getOwner()->getId()) {
		$singleStudent[] = $currentStudent;

		break;
	}
}

$dueWorkDetail = new DueWork_Detail($submissionBoxId);
$dueWorkDetail->processStudents($singleStudent);
$dueWorkDetail->renderDetails($this);

$userMapper = new \Schoolbox\UserMapper();
$user = $userMapper->findById($submission->getOwner()->getId());

$this->setVar(array(
	'SUBMISSION_ID' => $this->arg[0],
	'STUDENT_FULL_NAME' => $user->getFullName()
));