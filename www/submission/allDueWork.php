<?php
require_once SITE_root . '/library/submission.php';
require_once SITE_root . '/library/submissionReturn.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/user.php';

$this->setFile(TEMPLATE_content, 'submission/allDueWork.html');
$this->setTab('submission');

$this->setBlock(TEMPLATE_content, 'subject', 'subject_block');
$this->setBlock('subject', 'work', 'work_block');
$this->setBlock('work', 'download', 'download_block');
$this->setBlock('work', 'status_due', 'status_due_block');
$this->setBlock('work', 'status_awaiting_review', 'status_awaiting_review_block');
$this->setBlock('work', 'status_marked', 'status_marked_block');

$userHandler = new User;
$groupHandler = new Group;
$submissionHandler = new Submission;
$returnHandler = new SubmissionReturn;

/* ideally session children would use the id as the array key as this is ridiculous */
$childList = array();

if (is_array($_SESSION['children'])) {
	foreach ($_SESSION['children'] as $child) {
		$childList[] = $child['id'];
	}
}

// get selected user
if (is_array($_SESSION['children'])) {
    $userID = $_SESSION['children'][0]['id'];
}
else {
    //displaying users on due work, so log this as the last time they viewed due work
    $submissionHandler->logPageView(1);
    $submissionHandler->logPageView(2);
    $userID = $_SESSION['id'];
}

if (is_numeric($this->arg[1]) && in_array($this->arg[1], $childList)) {
	$userID = $this->arg[1];
}
else if (is_numeric($this->arg[1]) && $groupHandler->isStaff()) {
	$userID = $this->arg[1];
}

$user = $userHandler->getByID($userID);

if (!isset($user['uid'])) {
	error(2);
}

if (is_array($_SESSION['children'])) {
	$this->dropDown('child', $_SESSION['children'], $user['uid'], 'is_parent', array('id' => 'CHILD_ID', 'name' => 'CHILD_NAME'));

	$this->parse('is_parent_block', 'is_parent');
}

// get category list
$subjectList = false;

if (Cfg::get('external_enable') && $user['synergy_id'])  {
        require_once SITE_root . '/library/'.Cfg::get('external_type').'/ExternalUser.php';
	$synUser = new ExternalUser;
	$subjectList = $synUser->getSubjectList($user['synergy_id'], $user['uid']);
}

$categoryList = $groupHandler->involvedCategoryList($user['uid'], $subjectList, 1);

if (is_array($categoryList)) {
	foreach($categoryList as $category) {
		$categoryIDList[] = $category['id'];
	}
}

// Catch if not involved in any categories
if (count($categoryList) == 0) {
	error(135);
}


$allWork = $submissionHandler->getAllDueWork($userID, $categoryIDList);

if (is_array($allWork)) {
	$prevSubject = false;
	$first = true;
	$j = 0;

	foreach($allWork as $currentWork) {

		/* Start a new subject heading?
		 */
		if (($previousSubject != $currentWork['categoryName']) && (!$first)){
			$previousSubject = $currentWork['categoryName'];

			$this->parse('subject_block', 'subject', true);
			$this->setVar('work_block', '');
		}

		$this->setVar(array(
			'WORK_CATEGORYNAME' => $currentWork['categoryName'],
			'WORK_DESCRIPTION' => $currentWork['description'],
			'WORK_DUEDATE' => outputDate(DATE_short_no_time, $currentWork['dueDate']),
			'WORK_MARK' => $currentWork['mark'],
			'WORK_ACTION' => 'bleah',
			'WORK_BOX_ID' => $currentWork['bid'],
			'WORK_FILE_ID' => $currentWork['fid'],
			'WORK_SUBMIT_FILE_ID' => $currentWork['sid'],
			'WORK_RETURN_FILE_ID' => $currentWork['rid'],
		));

		if ($currentWork['submitted'] != null) {
			if ($currentWork['returned'] != null) {
				$this->setVar('status_due_block', '');
				$this->setVar('status_awaiting_review_block', '');
				$this->parse('status_marked_block', 'status_marked');
			}
			else {
				$this->setVar('status_due_block', '');
				$this->parse('status_awaiting_review_block', 'status_awaiting_review');
				$this->setVar('status_marked_block', '');
			}
		}
		else {
			$this->parse('status_due_block', 'status_due');
			$this->setVar('status_awaiting_review_block', '');
			$this->setVar('status_marked_block', '');
		}

		/* Alternating row color
		 */
		$this->setVar(array(
			'ROW_COLOR' => !($j%2) ? 'dark' : ''
		));

		$this->parse('work_block', 'work', true);

		$this->setVar('status_due_block', '');
		$this->setVar('status_awaiting_review_block', '');
		$this->setVar('status_marked_block', '');

		$first = false;
		$j++;
	}
}

if (is_array($allWork) ) {
    $this->parse('subject_block', 'subject', true);
}
else {
    $this->setVar('subject_block','');
}

/* Online Assessments
 */
require_once(SITE_root . '/library/assess/assess.php');
require_once(SITE_root . '/library/assess/assess-attempt.php');
require_once(SITE_root . '/library/assess/assess-response.php');
require_once(SITE_root . '/library/assess/assess-section.php');

$this->setBlock(TEMPLATE_content, 'allAssess', 'allAssess_block');
$this->setBlock('allAssess', 'attempt', 'attempt_block');
$this->setBlock('attempt', 'viewMarks', 'viewMarks_block');
$this->setBlock('attempt', 'takeTest', 'takeTest_block');
$this->setBlock('attempt', 'unmarked', 'unmarked_block');

$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$responseHandler = new AssessResponse();
$sectionHandler = new AssessSection();

/* Store a lookup so you don't need to make new queries all day
 */
foreach($categoryList as $key => $value) {
    $categoryLookup[$value['id']] = $value['name'];
}

/* Make a list of assessments
 */
$allAssessments = $assessHandler->getList($categoryIDList, 10);

if ($allAssessments) {
    foreach($allAssessments as $currentAssessment) {
        $currentAttempt = $attemptHandler->getLatestForUser($currentAssessment['assess_id'], $_SESSION['id']);

        $firstSection = $sectionHandler->getFirst($currentAssessment['assess_id']);

        if (!$currentAttempt) {
            $this->setVar(array(
                'ASSESS_CATEGORY' => $categoryLookup[$currentAssessment['category_id']],
                'ASSESS_ASSESS_ID' => $currentAssessment['assess_id'],
                'ASSESS_TITLE' => $currentAssessment['title'],
                'ASSESS_FINISH' => '',
                'ASSESS_FIRST_SECTION_ID' => $firstSection['section_id'],
                'ATTEMPT_ATTEMPT_ID' => $currentAttempt['attempt_id'],
                'ATTEMPT_TOTAL_MARKS' => number_format(0, 2),
                'ATTEMPT_TOTAL_AVAILABLE_MARKS' => number_format($totalAvailableMarks, 2),
                'ATTEMPT_PERCENTAGE' => 0,
            ));

            $this->setVar('viewMarks_block', '');
            $this->parse('takeTest_block', 'takeTest');
            $this->setVar('unmarked_block', '');
        }
        else {
            /* Finish time
             */
            if ($currentAttempt['finish'] != '0000-00-00 00:00:00') {
                $finish = date('jS F Y, g:i:sa', strtotime($currentAttempt['finish']));
                $timeTaken = $assessHandler->sec2hms($currentAttempt['timeTaken']);
            }
            else {
                $finish = '';
                $timeTaken = '';
            }

            /* The total available marks for this attempt
             */
            $totalAvailableMarks = $attemptHandler->getTotalMarks($currentAssessment['assess_id'], $currentAttempt['attempt_id']);

            if ($totalAvailableMarks > 0) {
                $marks = $attemptHandler->getMarksForAttempt($currentAttempt['attempt_id']);

                $percentage = round(100 * $marks / $totalAvailableMarks);
            }
            else {
                $percentage = 0;
            }

            /* Count how many responses haven't been automarked
             */
            $numNotAutomarked = $responseHandler->numNotAutomarked($currentAttempt['attempt_id']);

            if ($numNotAutomarked == 0) {
                $this->setVar(array(
                    'ATTEMPT_UNMARKED_RESPONSES' => $numNotAutomarked
                ));

                $this->parse('unmarked_block', 'unmarked');
            }
            else {
                $this->setVar('unmarked_block', '');
            }

            $this->setVar(array(
                'ASSESS_CATEGORY' => $categoryLookup[$currentAssessment['category_id']],
                'ASSESS_ASSESS_ID' => $currentAssessment['assess_id'],
                'ASSESS_TITLE' => $currentAssessment['title'],
                'ASSESS_FINISH' => date('jS F Y, g:i:sa', strtotime($currentAttempt['finish'])),
                'ASSESS_FIRST_SECTION_ID' => $firstSection['section_id'],
                'ATTEMPT_ATTEMPT_ID' => $currentAttempt['attempt_id'],
                'ATTEMPT_TOTAL_MARKS' => number_format($marks, 2),
                'ATTEMPT_TOTAL_AVAILABLE_MARKS' => number_format($totalAvailableMarks, 2),
                'ATTEMPT_PERCENTAGE' => $percentage,
            ));

            $this->setVar('takeTest_block', '');

            /* Find out if this test has been marked; if it has, allow the
             * student to see the feedback, otherwise remove link altogether
             */
            if ($currentAttempt['reviewed_at']) {
                $this->parse('viewMarks_block', 'viewMarks');
            }
            else {
                $this->setVar('viewMarks_block', '');
            }
        }

        $this->parse('attempt_block', 'attempt', true);
    }

    $this->parse('allAssess_block', 'allAssess');
}
else {
    $this->setVar('allAssess_block', '');
}
?>