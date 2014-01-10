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

/* This is an optional argument
 */
$submissionId = $this->arg[2];

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

		/* Augment navigation data
		 */
		$currentStudent['navigation'] = array(
			'id' => $currentStudent['uid'],
			'label' => $currentStudent['lastname'] . ', ' . $currentStudent['firstname'],
			'value' => '/submission/inline/view/' . $submissionBoxId . '/' . $currentStudent['uid']
		);

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

		/* Load a specific submission for this box/person
		 */
		if (is_numeric($this->arg[2])) {
			$submission = $submissionMapper->findById($this->arg[2]);

			if ($submission) {
				$currentStudent['submission'] = $submission;
			}
		}
		else {
			$collection = $submissionMapper->getHistory($currentStudent['uid'], $submissionBoxId);

			/* Get the latest submission, if one exists
			 */
			if ($collection->count()) {
				$currentStudent['submission'] = $collection->first()->getSubmission();
			}
		}

		/* Previous
		 */
		$previousStudent = $stack[$i-1];

		/* Next
		 */
		$nextStudent = $stack[$i+1];

		break;
	}
}

/* Rendering things out
 */
$userMapper = new \Schoolbox\UserMapper();
$user = $userMapper->findById($this->arg[1]);

if ($currentStudent['submission'] != null) {
	$submissionId = $currentStudent['submission']->getId();
}

/* Data for the navigation selector
 */
$navigationItems = json_encode($stack);

$navigationElements = array(
	'previous' => $previousStudent,
	'current' => $currentStudent,
	'next' => $nextStudent,
);

$navigationDirect = json_encode($navigationElements);

$this->setFile(TEMPLATE_content, 'submission/inline/view.html');

$templateFactory = new \Crocodoc\Template\Renderer\Factory($this, $currentStudent['submission'], $navigationElements);
$renderer = $templateFactory->produce();
$renderer->render();

$this->setVar(array(
	'CROCODOC_SUBMISSION_ID' => $submissionId,
	'CROCODOC_SUBMISSION_BOX_ID' => $submissionBox['id'],
	'CROCODOC_SUBMISSION_NAVIGATION_CURRENT_ID' => $currentStudent['uid'],
	'CROCODOC_SUBMISSION_NAVIGATION_DIRECT' => $navigationDirect,
	'CROCODOC_SUBMISSION_NAVIGATION_ITEMS' => $navigationItems,
	'STUDENT_FULL_NAME' => $user->getFullName(),
));

$dueWorkDetail = new DueWork_Detail($submissionBoxId);
$dueWorkDetail->processStudents(array($currentStudent));
$dueWorkDetail->renderDetails($this);