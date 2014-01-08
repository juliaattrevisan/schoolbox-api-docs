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

for ($i=0; $i < count($stack); $i++) {
	$currentStudent = $stack[$i];

	if ($currentStudent['uid'] == $submission->getOwner()->getId()) {
		$collection = $submissionMapper->getHistory($currentStudent['uid'], $submissionBoxId);
		$currentStudent['submission'] = $collection->first();

		$singleStudent[] = $currentStudent;

		/* For navigation purposes
		 * Get their latest submissions, if any exist
		 */
		$previousStudent = $stack[$i-1];

		if ($previousStudent !== null) {
			$collection = $submissionMapper->getHistory($previousStudent['uid'], $submissionBoxId);
			$previousStudent['submission'] = $collection->first();
		}

		$nextStudent = $stack[$i+1];

		if ($nextStudent !== null) {
			$collection = $submissionMapper->getHistory($nextStudent['uid'], $submissionBoxId);
			$nextStudent['submission'] = $collection->first();
		}

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

new \Alaress_Probe($currentStudent);
new \Alaress_Probe($previousStudent);
new \Alaress_Probe($nextStudent);
