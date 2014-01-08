<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* /submission/inline/view/<box_id>/<user_id>
 */
$submissionBoxId = $this->arg[0];
$currentUid = $this->arg[1];

$submissionHandler = new Submission();
$submissionBox = $submissionHandler->getById($submissionBoxId);

$studentList = $submissionHandler->getAllStudentsForSubmission($submissionBox);

/* Uniqueify list (why this returns dupes, I'm not entirely sure)
 */
$processed = array();
$stack = array();

foreach ($studentList as $currentStudent) {
	if (!in_array($currentStudent['uid'], $processed)) {
		$processed[] = $currentStudent['uid'];

		$stack[] = $currentStudent;
	}
}

/* Trim out only this student
 */
$singleStudent = array();
$submissionMapper = new \Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();

for ($i=0; $i < count($stack); $i++) {
	$currentStudent = $stack[$i];

	if ($currentStudent['uid'] == $this->arg[1]) {

		$collection = $submissionMapper->getHistory($currentStudent['uid'], $submissionBoxId);

		/* Get the latest submission, if one exists
		 */
		if ($collection->count()) {
			$currentStudent['submission'] = $collection->first()->getSubmission();
		}

		$singleStudent[] = $currentStudent;

		/* For navigation purposes
		 */
		$previousStudent = $stack[$i-1];
		$nextStudent = $stack[$i+1];

		break;
	}
}

/* Rendering things out
 */
$userMapper = new \Schoolbox\UserMapper();
$user = $userMapper->findById($this->arg[1]);

$this->setFile(TEMPLATE_content, 'submission/inline/view.html');

if ($currentStudent['submission'] != null) {
	$submissionId = $currentStudent['submission']->getId();
}

$this->setVar(array(
	'SUBMISSION_ID' => $submissionId,
	'STUDENT_FULL_NAME' => $user->getFullName()
));

$dueWorkDetail = new DueWork_Detail($submissionBoxId);
$dueWorkDetail->processStudents($singleStudent);
$dueWorkDetail->renderDetails($this);