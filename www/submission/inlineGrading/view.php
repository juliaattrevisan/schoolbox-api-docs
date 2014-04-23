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

$submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
$submissionBox = $submissionBoxMapper->findById($submissionBoxId);

/* Crappy handler
 */
$submissionHandler = new Submission();
$studentList = $submissionHandler->getAllStudentsForSubmission($submissionBox);

/* Uniqueify list (why this returns dupes, I'm not entirely sure)
 */
$processed = array();
$stack = array();

foreach ($studentList as $currentStudent) {
	if (!in_array($currentStudent->getId(), $processed)) {
		$processed[] = $currentStudent->getId();

		/* Augment navigation data
		 */
		$augmented = array(
			'currentStudent' => $currentStudent,
			'submission' => null,
			'navigation' => array(
				'id' => $currentStudent->getId(),
				'label' => $currentStudent->getLastname() . ', ' . $currentStudent->getFirstname(),
				'value' => '/submission/inlineGrading/view/' . $submissionBoxId . '/' . $currentStudent->getId()
			),
		);

		$stack[] = $augmented;
	}
}

/* Trim out only this student
 */
$submissionMapper = new \Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();

for ($i=0; $i < count($stack); $i++) {
	$currentAugmented = $stack[$i];

	if ($currentAugmented['currentStudent']->getId() == $this->arg[1]) {

		/* Load a specific submission for this box/person
		 */
		if (is_numeric($this->arg[2])) {
			$submission = $submissionMapper->findById($this->arg[2]);

			if ($submission) {

				/* Apply mime type
				 */
				$mimetypeMapper = new \Schoolbox\File\MimetypeMapper();
				$mime = $mimetypeMapper->getByFilename($submission->getFilename());
				$submission->setMimetype($mime);

				/* Stack it
				 */
				$currentStudent['submission'] = $submission;
			}
		}
		else {
			$collection = $submissionMapper->getHistory($currentAugmented['currentStudent']->getId(), $submissionBoxId);

			/* Get the latest submission, if one exists
			 */
			if ($collection->count()) {
				$currentAugmented['submission'] = $collection->first()->getSubmission();
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

if ($currentAugmented['submission'] != null) {
	$submissionId = $currentAugmented['submission']->getId();
}

/* Data for the navigation selector
 */
$navigationItems = json_encode($stack);

$navigationElements = array(
	'boxId' => $submissionBox->getId(),
	'previous' => $previousStudent,
	'current' => $currentAugmented['currentStudent'],
	'next' => $nextStudent,
);

$navigationDirect = json_encode($navigationElements);

$this->setFile(TEMPLATE_content, 'submission/inlineGrading/view.html');

/* Render injectable views
 */
$templateFactory = new \Crocodoc\Renderer\Factory($this, $currentAugmented['submission'], $navigationElements);
$renderer = $templateFactory->produce();
$renderer->render();

/* Assign marks
 */
$oneStudent = new \Schoolbox\Collection();
$oneStudent->add($currentAugmented['currentStudent']);

$dueWorkDetail = new DueWork_Detail($submissionBoxId);
$dueWorkDetail->processStudents($oneStudent);
$dueWorkDetail->renderDetails($this);

/* Rubric's Cube
 */
$rubricsRenderer = new \Rubric\Renderer\Vertical($this, $submissionBox, $user);
$rubricsRenderer->render()->parseBlock();

/* Determine redirect based on whether you're at the end of the list or not
 * Personally I think it'd be better to wrap back to the start, instead of
 * getting kicked out - then you can review the marks in a 'horizontal' manner
 */
if (is_array($nextStudent)) {
	$nextUrl = '/submission/inlineGrading/view/' . $submissionBox->getId() . '/' . $nextStudent['uid'];
}
else {
	$nextUrl = '/submission/viewSubmission/' . $submissionBox->getId();
}

$this->setVar(array(
	'INLINE_GRADING_SUBMISSION_ID' => $submissionId,
	'INLINE_GRADING_SUBMISSION_BOX_ID' => $submissionBox->getId(),
	'INLINE_GRADING_SUBMISSION_BOX_NAME' => $submissionBox->getName(),
	'INLINE_GRADING_SUBMISSION_NAVIGATION_CURRENT_ID' => $currentAugmented['currentStudent']->getId(),
	'INLINE_GRADING_SUBMISSION_NAVIGATION_DIRECT' => $navigationDirect,
	'INLINE_GRADING_SUBMISSION_NAVIGATION_ITEMS' => $navigationItems,
	'INLINE_GRADING_STUDENT_FULL_NAME' => $user->getFullName(),
	'INLINE_GRADING_STUDENT_ID' => $user->getId(),
	'INLINE_GRADING_SUBMISSION_NAVIGATION_NEXT_URL' => $nextUrl
));

