<?php

$this->setFile(TEMPLATE_content, 'submission/allDueWork.html');
$this->setTab('submission');
$this->setBlock(TEMPLATE_content, 'subject', 'subject_block');
$this->setBlock('subject', 'work', 'work_block');
$this->setBlock('work', 'download', 'download_block');
$this->setBlock('work', 'status_due', 'status_due_block');
$this->setBlock('work', 'status_awaiting_review', 'status_awaiting_review_block');
$this->setBlock('work', 'status_marked', 'status_marked_block');

$userHandler = new User();
$groupHandler = new Group();
$submissionHandler = new Submission();
$returnHandler = new SubmissionReturn();

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

if (is_numeric($this->arg[0]) && in_array($this->arg[0], $childList)) {
	$userID = $this->arg[0];
}
else if (is_numeric($this->arg[0]) && $groupHandler->isStaff()) {
	$userID = $this->arg[0];
}

$user = $userHandler->getByID($userID);

if (!isset($user['uid'])) {
	error(2);
}

/* Check if system config restrict_student_submissions is on
 */
if ($groupHandler->isParentOfChild($this->arg[0])) {
	if (Cfg::get('restrict_student_submissions')) {
		error(19);
	}
}

$categoryList = $groupHandler->involvedInCategoryList($userID, true, READ_ACCESS);

if (is_array($categoryList)) {
	foreach($categoryList as $category) {
		$categoryIDList[] = $category['id'];
	}
}

// Catch if not involved in any categories
if (count($categoryList) == 0) {
	error(135);
}

$this->setVar(array('WORK_USER_ID'  => $userID,
                    'WORK_USER_NAME'=> $userID==$_SESSION['id']?'My':$userHandler->formatName($user)."'s",
              ));

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

		$dueDate = new Alaress_Date_Format($currentWork['dueDate'], 'U');

		$this->setVar(array(
			'WORK_CATEGORYNAME' => Scrub::Text($currentWork['categoryName']),
			'WORK_DESCRIPTION' => Scrub::Text($currentWork['name']),
			'WORK_DUEDATE' => $dueDate->getDateOnly(),
			'WORK_MARK' => $currentWork['mark'],
			'WORK_ACTION' => '',
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
$this->setBlock(TEMPLATE_content, 'allAssess', 'allAssess_block');
$this->setBlock('allAssess', 'attempt', 'attempt_block');
$this->setBlock('attempt', 'viewMarks', 'viewMarks_block');
$this->setBlock('attempt', 'takeTest', 'takeTest_block');
$this->setBlock('attempt', 'unmarked', 'unmarked_block');

$assessHandler = new Assess_Main();
$attemptHandler = new Assess_Attempt();
$responseHandler = new Assess_Response();
$sectionHandler = new Assess_Section();

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
		if ($currentAssessment['allow_resit']) {
			$allAttempts = $attemptHandler->getListForUser($currentAssessment['assess_id'], $_SESSION['id']);
		}
		else {
        	$allAttempts = array($attemptHandler->getLatestForUser($currentAssessment['assess_id'], $_SESSION['id']));
		}

        $firstSection = $sectionHandler->getFirst($currentAssessment['assess_id']);

		/* Iterate
		 */
        if (!is_array($allAttempts[0])) {
            $this->setVar(array(
                'ASSESS_CATEGORY' => $categoryLookup[$currentAssessment['category_id']],
                'ASSESS_ASSESS_ID' => $currentAssessment['assess_id'],
                'ASSESS_TITLE' => Scrub::Text($currentAssessment['title']),
                'ASSESS_FINISH' => '',
                'ASSESS_FIRST_SECTION_ID' => $firstSection['section_id'],
                'ATTEMPT_ATTEMPT_ID' => '',
                'ATTEMPT_TOTAL_MARKS' => number_format(0, 2),
                'ATTEMPT_TOTAL_AVAILABLE_MARKS' => number_format($totalAvailableMarks, 2),
                'ATTEMPT_PERCENTAGE' => 0,
            ));

            $this->setVar('viewMarks_block', '');
            $this->parse('takeTest_block', 'takeTest');
            $this->setVar('unmarked_block', '');
			$this->parse('attempt_block', 'attempt', true);
        }
        else {
			if (is_array($allAttempts) && count($allAttempts)) {
				foreach($allAttempts as $currentAttempt) {

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

					/* Count how many responses haven't been automarked, if it hasn't been
					 * reviewed yet.
					 */
					if ($currentAttempt['reviewed_at'] == '') {
						$this->setVar(array(
							'ATTEMPT_UNMARKED_RESPONSES' => $responseHandler->numNotAutomarked($currentAttempt['attempt_id'])
						));

						$this->parse('unmarked_block', 'unmarked');
					}
					else {
						$this->setVar('unmarked_block', '');
					}

					$this->setVar(array(
						'ASSESS_CATEGORY' => $categoryLookup[$currentAssessment['category_id']],
						'ASSESS_ASSESS_ID' => $currentAssessment['assess_id'],
						'ASSESS_TITLE' => Scrub::Text($currentAssessment['title']),
						'ASSESS_FINISH' => $finish,
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

					$this->parse('attempt_block', 'attempt', true);
				}
			}
			else {
				$this->setVar('attempt_block', '');
			}
        }
    }

    $this->parse('allAssess_block', 'allAssess');
}
else {
    $this->setVar('allAssess_block', '');
}

