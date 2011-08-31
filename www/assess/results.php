<?php
/**
 * Results page. This provides a list of Attempts that were made for a
 * particular Assessment, and the total marks obtained by the student.
 * Automatic marking of tests is handled here, where possible; otherwise, a
 * manual review is required to evaluate marks for certain questions which
 * cannot be automatically marked, e.g. short/long answer question types.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/assess/Assess.php');
require_once('../library/assess/AssessFactory.php');
require_once('../library/assess/AssessAttempt.php');
require_once('../library/assess/AssessResponse.php');
require_once('../library/assess/AssessSection.php');
require_once('../library/group.php');
require_once('../library/category.php');
require_once('../library/user.php');

/**
 * Process an Attempt row. Functionised because it is used in an iteration to
 * process students in the class, and then later used to deal with remainder
 * attempts in a separate iteration.
 *
 * @param <type> $assessHandler
 * @param <type> $attemptHandler
 * @param <type> $responseHandler
 * @param <type> $sectionHandler
 * @param <type> $currentAttempt
 * @param <type> $page
 */
function processAttempt($assessHandler, $attemptHandler, $responseHandler, $sectionHandler, $currentAttempt, $page) {
	$firstSection = $sectionHandler->getFirst($page->arg[0]);

	/* Finish time. Show the datetime when the person clicked Finish.
	 * Otherwise, show the last time their responses were updated, or that
	 * they never even attempted the test.
	 */
	if ($currentAttempt['finish'] != '0000-00-00 00:00:00') {
		$finish = date('jS F Y, g:i:sa', strtotime($currentAttempt['finish']));
		$timeTaken = $assessHandler->sec2hms($currentAttempt['timeTaken']);
	}
	else {
		$finish = $responseHandler->getLastSave($currentAttempt['attempt_id']);

		if (empty($finish)) {
			$finish = 'Unfinished';
		}
		else {
			$finish = date('jS F Y, g:i:sa', strtotime($finish));
		}

		$timeTaken = 'Incomplete';
	}

	/* The total available marks for this attempt
	 */
	$totalAvailableMarks = $attemptHandler->getTotalMarks($page->arg[0], $currentAttempt['attempt_id']);

	if ($totalAvailableMarks > 0) {
		$percentage = round(100 * $currentAttempt['totalMarks'] / $totalAvailableMarks);
	}
	else {
		$percentage = 0;
	}

	/* Check when the test was reviewed
	 */
	if ($currentAttempt['reviewed_at'] != '') {
		$reviewedAt = 'Reviewed on ' . date('jS F Y, g:i:sa', strtotime($currentAttempt['reviewed_at']));
		
	}
	else {
		$reviewedAt = 'Not reviewed';
	}

	/* Count how many responses haven't been automarked
	 */
	$numNotAutomarked = $responseHandler->numNotAutomarked($currentAttempt['attempt_id']);

	$page->setVar(array(
		'ASSESS_ASSESS_ID' => $page->arg[0],
		'ASSESS_FIRST_SECTION_ID' => $firstSection['section_id'],
		'ATTEMPT_ATTEMPT_ID' => $currentAttempt['attempt_id'],
		'USER_ID' => $currentStudent['uid'],
		'ATTEMPT_NAME' => $currentAttempt['lastname'] . ', ' . $currentAttempt['firstname'],
		'ATTEMPT_FINISH' => $finish,
		'ATTEMPT_TIME_TAKEN' => $timeTaken,
		'ATTEMPT_TOTAL_MARKS' => number_format($currentAttempt['totalMarks'], 2),
		'ATTEMPT_TOTAL_AVAILABLE_MARKS' => number_format($totalAvailableMarks, 2),
		'ATTEMPT_PERCENTAGE' => $percentage,
		'ATTEMPT_REVIEW_STATUS' => $reviewedAt,
		'ATTEMPT_UNMARKED_RESPONSES' => $numNotAutomarked,
		'ATTEMPT_REVIEW_LINK' => "<a href=\"/assess/review/{$page->arg[0]}/{$firstSection['section_id']}/{$currentAttempt['attempt_id']}\">Review</a>",
		'ATTEMPT_DELETE_LINK' => "<a href=\"/assess/deleteAttempt/{$currentAttempt['attempt_id']}\">Delete</a>",
	));

	$page->parse('attempt_block', 'attempt', true);
}

/* Handlers and stuff
 */
$factory = new AssessFactory();
$responseHandler = new AssessResponse();
$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$sectionHandler = new AssessSection();
$groupHandler = new Group();
$categoryHandler = new Category();

$assess = $assessHandler->getByID($this->arg[0]);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

if (!$assessHandler->isOwner($this->arg[0])) {
    error(125);
}

/* Set up template
 */
$this->setFile(TEMPLATE_content, 'assess/results.html');
$this->setBlock(TEMPLATE_content, 'attempt', 'attempt_block');
$this->setTitle('Results for: ' . $assess['title']);

$this->setVar(array(
	'ASSESS_ASSESS_ID' => $assess['assess_id'],
    'ASSESS_TITLE' => $assess['title'],
));

/* Get a list of students who could have submitted data to this Assess, then
 * join up all the attempts via php, so we can see who has submitted to this
 * Assess but are no longer in the class
 */
$categoryCode = $categoryHandler->getCodeForCategory($assess['category_id']);

if (($categoryCode['codes'] != '') && Cfg::get('external_enable')) {
	require_once SITE_root . '/library/'.Cfg::get('external_type').'/ExternalClass.php';

	//if synergy code get synergy students
	$synHandler = new ExternalClass();
	$allStudents = $synHandler->getStudents($categoryCode['codes']);
}
else {
	//Retrieve student list from group membership
	$allStudents = $groupHandler->getMemberList($assess['category_id']);
}

/* Now construct list of data
 */
$allAttempts = $attemptHandler->getList($this->arg[0]);

if (is_array($allStudents) && count($allStudents)) {
	foreach($allStudents as $currentStudent) {

		/* Get the actual user by the uid if it exists, or the synergy id if
		 * they don't have a portal account
		 */
		if ($currentStudent['uid']) {
			$user = $userHandler->getByID($currentStudent['uid']);
		}
		else if ($currentStudent['synergy_id']) {
			$user = $userHandler->getBySynergy($currentStudent['synergy_id']);
		}

		/* Process attempts for this student
		 */
		if ($allAttempts && count($allAttempts)) {
			for ($numAttempts = 0, $i = 0; $i < count($allAttempts); $i++) {
				$currentAttempt = $allAttempts[$i];

				/* Match up attempt with student then remove from list
				 */
				if ($currentAttempt['user_id'] == $currentStudent['uid']) {
					if ($allAttempts[$i]['processed'] != true) {
						processAttempt($assessHandler, $attemptHandler, $responseHandler, $sectionHandler, $currentAttempt, $this);

						$allAttempts[$i]['processed'] = true;

						$numAttempts++;
					}
				}
			}
		}

		/* If no attempts, just show name
		 */
		if (!$numAttempts) {
			$this->setVar(array(
				'ASSESS_ASSESS_ID' => '',
				'ASSESS_FIRST_SECTION_ID' => '',
				'ATTEMPT_ATTEMPT_ID' => '',
				'USER_ID' => $currentStudent['uid'],
				'ATTEMPT_NAME' => $currentStudent['firstname'] . ' ' . $currentStudent['lastname'],
				'ATTEMPT_FINISH' => '',
				'ATTEMPT_TIME_TAKEN' => '',
				'ATTEMPT_TOTAL_MARKS' => '',
				'ATTEMPT_TOTAL_AVAILABLE_MARKS' => '',
				'ATTEMPT_PERCENTAGE' => '',
				'ATTEMPT_REVIEW_STATUS' => '',
				'ATTEMPT_UNMARKED_RESPONSES' => '',
				'ATTEMPT_REVIEW_LINK' => '',
				'ATTEMPT_DELETE_LINK' => '',
			));

			$this->parse('attempt_block', 'attempt', true);
		}

		$numAttempts = 0;
	}
}
else {
	$this->setVar('attempt_block', '');
}

/* Deal with remainder attempts
 */
if ($allAttempts && count($allAttempts)) {
	foreach($allAttempts as $currentAttempt) {
		if ($currentAttempt['processed'] != true) {
			processAttempt($assessHandler, $attemptHandler, $responseHandler, $sectionHandler, $currentAttempt, $this);
		}
	}
}
?>
