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

/* Add category codes
 */
$categoryCodeMapper = new \Schoolbox\Resource\CategoryCodeMapper();
$categoryCode = $categoryCodeMapper->findForCategory($submissionBox->getCategory()->getId());
$submissionBox->getCategory()->setCodes($categoryCode);

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
				$mime = $mimetypeMapper->findOneByColumn('mime', $submission->getMimeType()->getMime());
				$submission->setMimetype($mime);

				/* Stack it
				 */
				$currentAugmented['submission'] = $submission;
			}
		}
		else {
			$collection = $submissionMapper->getHistory($currentAugmented['currentStudent']->getId(), $submissionBoxId);

			/* Get the latest submission, if one exists
			 */
			if ($collection->count()) {
				$currentAugmented['submission'] = $collection->first()->getSubmission();
                $this->arg[2] = $collection->first()->getSubmission()->getId();
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

/* Find the latest returned work
 */
$latestReturnedWork = null;

$returnWorkMapper = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper();
$latestReturnedActivity = $returnWorkMapper->getHistory($user->getId(), $submissionBox->getId())->first();

if ($latestReturnedActivity instanceof \Schoolbox\Assessment\Activity) {
	$latestReturnedWork = $latestReturnedActivity->getReturnedWork();
}

/* Augment array with data
 */
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
$templateFactory = new \Crocodoc\Renderer\Factory($this, $currentAugmented['submission'], $latestReturnedWork, $navigationElements);
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
	$nextUrl = '/submission/inlineGrading/view/' . $submissionBox->getId() . '/' . $nextStudent['currentStudent']->getId();
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

/* Skip Unsubmitted checkbox
 * Find out who should be skipped and pass them to the js
 */
if ($submissionBox->getSkipUnsubmitted()) {

	/* Remember everyone who hasn't submitted
	 */
	$noSubmissions = array();

	/* Find all the submissions to this submission box
	 */
	$allSubmissions = $submissionMapper->findByColumn('box_id', $submissionBox->getId());

	/* Get a unique list of users who submitted to this box
	 */
	$ids = array();

	foreach($allSubmissions as $currentSubmission) {
		$ids[$currentSubmission->getOwner()->getId()] = '';
	}

	$ids = array_keys($ids);

	/* Find all the people from the hybrid list who didn't exist in $ids
	 */
	if (count($ids)) {
		foreach($stack as $current) {
			if (!in_array($current['currentStudent']->getId(), $ids)) {
				$noSubmissions[] = $current['currentStudent']->getId();
			}
		}
	}

	/* Parse block
	 */
	$this->setVar(array(
		'SKIP_UNSUBMITTED_CHECKED' => 'checked="checked"',
		'INLINE_GRADING_SUBMISSION_NAVIGATION_DISABLED' => json_encode($noSubmissions),
	));
}
/* Otherwise nobody is disabled
 */
else {
	$this->setVar(array(
		'SKIP_UNSUBMITTED_CHECKED' => '',
		'INLINE_GRADING_SUBMISSION_NAVIGATION_DISABLED' => json_encode(array()),
	));
}
