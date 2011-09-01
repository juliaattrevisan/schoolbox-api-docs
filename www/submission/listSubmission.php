<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/assess/Assess.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/assess/AssessAttempt.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';



$this->setFile(TEMPLATE_content, 'submission/listSubmission.html');
$this->setTitle('List of Submission Boxes');
$this->setTab('submission');
$this->setBlock(TEMPLATE_content, 'submission_listing', 'submission_block');
$this->setBlock(TEMPLATE_content, 'category_listing', 'category_block');
$this->setBlock('submission_listing', 'onlineAssessment', 'onlineAssessment_block');
$this->setBlock('submission_listing', 'normalSubmission', 'normalSubmission_block');

$submissionHandler = new Submission;
$subList = $submissionHandler->getAllForUser($_SESSION['id']);

$attemptHandler = new AssessAttempt();
$groupHandler = new Group;

if(Cfg::get('external_enable')) {
    $synHandler = new ExternalClass();
}

if(is_array($subList)) {
    foreach($subList as $sub) {
        if($prevCat['catid'] != $sub['catid'] && $prevCat['catid'] != '') {

			 /* Get full student list of class to show those who have not submitted */
			if($prevCat['code'] && Cfg::get('external_enable')) {
				//if synergy code get synergy students
				$studentList = $synHandler->getStudents($prevCat['code']);
			}
			else {
				//Retrieve student list from group membership
				$studentList = $groupHandler->getMemberList($prevCat['catid']);
			}

			$this->setVar('STUDENTS', count($studentList));

			if($prevCat['code'] != "")
                    $this->setVar('CATEGORY', scrubTEXT($prevCat['name'])." (".$prevCat['code'].")");
            else
                    $this->setVar('CATEGORY', scrubTEXT($prevCat['name']));

            $this->setVar('CATEGORY_ID', $prevCat['catid']);

            $this->parse('category_block', 'category_listing', true);
            $this->setVar('submission_block', '');
            $i = 0;
        }

        $totals = $submissionHandler->getBoxCount($sub['id']);

        if ($sub['type_id'] == Assess::ONLINE_ASSESSMENT_TYPE_ID) {
            $this->setVar(array(
                'ASSESS_ASSESS_ID' => $sub['id'],
                'ASSESS_TITLE' => scrubTEXT($sub['name']),
                'ASSESS_TYPE' => $sub['type'],
                'JAVAASSDESC' => addslashes(scrubTEXT($sub['name'])),
                'ASSESS_START' => $sub['open_date'],
                'ASSESS_FINISH' => $sub['date'],
                'ASSESS_ATTEMPTS' => $attemptHandler->numAttempted($sub['id']),
                'ASSESS_REVIEWED' => $attemptHandler->numReviewed($sub['id']),
                'ON_OFF' => ++$i%2?'dark':'',
            ));

            $this->setVar('normalSubmission_block', '');
            $this->parse('onlineAssessment_block', 'onlineAssessment');
        }
        else {
            $this->setVar(array(
                'ID'          => $sub['id'],
                'ASSDESC'     => scrubTEXT($sub['name']),
                'TYPE'        => $sub['type'],
                'JAVAASSDESC' => addslashes(scrubTEXT($sub['name'])),
                'OPENDATE'    => $sub['accept_submission'] ? $sub['open_date'] : '',
                'DUEDATE'     => $sub['date'],
                'MARKED'      => (int) $totals['marked'],
                'TOTAL'       => $totals['total'],
                'ON_OFF'      => ++$i%2?'dark':'',
            ));

            $this->setVar('onlineAssessment_block', '');
            $this->parse('normalSubmission_block', 'normalSubmission');
        }

        $this->parse('submission_block', 'submission_listing', true);

        $prevCat = $sub;
    }

	/* Get full student list of class to show those who have not submitted */
	if($prevCat['code'] && Cfg::get('external_enable')) {
		//if synergy code get synergy students
		$studentList = $synHandler->getStudents($prevCat['code']);
	}
	else {
		//Retrieve student list from group membership
		$studentList = $groupHandler->getMemberList($prevCat['catid']);
	}

	$this->setVar('STUDENTS', count($studentList));

    if ($prevCat['code'] != "") {
        $this->setVar("CATEGORY", scrubTEXT($prevCat['name'])." (".$prevCat['code'].")");
    }
    else {
        $this->setVar("CATEGORY", scrubTEXT($prevCat['name']));
    }

    $this->setVar('CATEGORY_ID', $prevCat['catid']);

    $this->parse("category_block", "category_listing", true);
}
else {
    $this->setVar('category_block','');
}
?>
