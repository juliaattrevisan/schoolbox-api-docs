<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

if(isset($_GET['year']) || isset($_GET['semester'])) {
    $this->localRedir("/submission/listSubmission/{$_GET['year']}/{$_GET['semester']}");
}

$currentYear = date('Y');
$currentSemester = date('m') > 6 ? 2 : 1;

if(isset($this->arg[0])) {
    if(!isset($this->arg[0])) {
        error(2);
    }
    elseif(!(is_numeric($this->arg[0]) && is_numeric($this->arg[1]))) {
        error(2); // TODO find a more appropriate error
    }

    if($this->arg[1] < 1 || $this->arg[1] > 2) {
        error(2); // TODO find a more appropriate error
    }

    $year = (int)$this->arg[0];
    $semester = (int)$this->arg[1];
}
else {
    $year = $currentYear;
    $semester = $currentSemester;
}


$this->setFile(TEMPLATE_content, 'submission/listSubmission.html');
$this->setTitle('Submissions');
$this->setTab('submission');
$this->setBlock(TEMPLATE_content, 'submission_listing', 'submission_block');
$this->setBlock(TEMPLATE_content, 'category_listing', 'category_block');
$this->setBlock('submission_listing', 'onlineAssessment', 'onlineAssessment_block');
$this->setBlock('submission_listing', 'normalSubmission', 'normalSubmission_block');
$this->setBlock(TEMPLATE_content, 'semester_option', 'semester_option_block');
$this->setBlock(TEMPLATE_content, 'year_option', 'year_option_block');

$submissionHandler = new Submission();

// Semesters can be hardcoded, no need to look anything up (may change if a school wants Term breakdowns rather than Semester)
$semesters = array(1, 2);
foreach($semesters as $sem) {
    $this->setVar(array(
        'SEMESTER_SELECTED' => $sem == $semester ? ' selected="selected"' : '',
        'SEMESTER_VALUE'    => $sem
    ));
    $this->parse('semester_option_block','semester_option', true);
}

// Years is trickier, because we first need to find out all the years in which the teacher has ...taught
// while we figure that out, let's go with "the past 6 years"
for($y = $currentYear; $y > $currentYear - 6; --$y) {
    $this->setVar(array(
        'YEAR_SELECTED' => $y == $year ? ' selected="selected"' : '',
        'YEAR_VALUE'    => $y
    ));
    $this->parse('year_option_block', 'year_option', true);
}

$subList = $submissionHandler->getAllForUser($_SESSION['id'], $year, $semester);

$attemptHandler = new Assess_Attempt();
$groupHandler = new Group();

if (Cfg::get('external_enable')) {
	$synHandler = new External_Class();
}

if (is_array($subList)) {
	foreach ($subList as $sub) {
		if ($prevCat['catid'] != $sub['catid'] && $prevCat['catid'] != '') {

			/* Get full student list of class to show those who have not submitted
			 */
			if ($prevCat['code'] && Cfg::get('external_enable')) {
				/* If synergy code, get synergy students
				 */
				$studentList = $synHandler->getStudents($prevCat['code']);
			}
			else {
				/* Retrieve student list from group membership
				 */
				$studentList = $groupHandler->getMemberList($prevCat['catid']);
			}

			$this->setVar('STUDENTS', count($studentList));

			if ($prevCat['code'] != "") {
				$this->setVar('CATEGORY', Scrub::text($prevCat['category_name']) . " (" . $prevCat['code'] . ")");
			}
			else {
				$this->setVar('CATEGORY', Scrub::text($prevCat['category_name']));
			}

			$this->setVar('CATEGORY_ID', $prevCat['catid']);
			$this->parse('category_block', 'category_listing', true);
			$this->setVar('submission_block', '');

			$i = 0;
		}

		$totals = $submissionHandler->getBoxCount($sub['id']);

		if (($sub['type_id'] == Assess_Main::ONLINE_ASSESSMENT_TYPE_ID) && ($sub['availability'] == 'assess')) {
			$this->setVar(array(
				'ASSESS_ASSESS_ID' => $sub['id'],
				'ASSESS_TITLE' => Scrub::text($sub['name']),
				'ASSESS_TYPE' => $sub['type'],
				'JAVAASSDESC' => addslashes(Scrub::text($sub['name'])),
				'ASSESS_START' => $sub['open_date'],
				'ASSESS_FINISH' => $sub['date'],
				'ASSESS_ATTEMPTS' => $attemptHandler->numAttempted($sub['id']),
				'ASSESS_REVIEWED' => $attemptHandler->numReviewed($sub['id']),
				'ON_OFF' => ++$i % 2 ? 'dark' : '',
			));

			$this->setVar('normalSubmission_block', '');
			$this->parse('onlineAssessment_block', 'onlineAssessment');
		}
		else if ($sub['availability'] == '') {
			$this->setVar(array(
				'ID' => $sub['id'],
				'ASSDESC' => Scrub::text($sub['name']),
				'TYPE' => $sub['type'],
				'JAVAASSDESC' => addslashes(Scrub::text($sub['name'])),
				'OPENDATE' => $sub['accept_submission'] ? $sub['open_date'] : '',
				'DUEDATE' => $sub['date'],
				'MARKED' => (int)$totals['marked'],
				'TOTAL' => $totals['total'],
				'ON_OFF' => ++$i % 2 ? 'dark' : '',
			));

			$this->setVar('onlineAssessment_block', '');
			$this->parse('normalSubmission_block', 'normalSubmission');
		}
        else {
            // something went wrong (ie. this scenario should not exist) but at any rate, hide the tokens from view
            $this->setVar('onlineAssessment_block', '');
            $this->setVar('normalSubmission_block', '');
        }
		$this->parse('submission_block', 'submission_listing', true);

		$prevCat = $sub;
	}

	/* Get full student list of class to show those who have not submitted
	 */
	if ($prevCat['code'] && Cfg::get('external_enable')) {
		/* If synergy code, get synergy students
		 */
		$studentList = $synHandler->getStudents($prevCat['code']);
	}
	else {
		/* Retrieve student list from group membership
		 */
		$studentList = $groupHandler->getMemberList($prevCat['catid']);
	}

	$this->setVar('STUDENTS', count($studentList));

	if ($prevCat['code'] != "") {
		$this->setVar("CATEGORY", Scrub::text($prevCat['category_name']) . " (" . $prevCat['code'] . ")");
	}
	else {
		$this->setVar("CATEGORY", Scrub::text($prevCat['category_name']));
	}

	$this->setVar('CATEGORY_ID', $prevCat['catid']);

	$this->parse("category_block", "category_listing", true);
}
else {
	$this->setVar('category_block', 'No submissions found.');
}
