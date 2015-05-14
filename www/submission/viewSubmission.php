<?php

if (!isset($this)) {
    require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
    SitePage::localRedir('/submission/viewSubmission');
}

$submissionBoxId = $this->arg[0];

// check the ID is valid
if (!is_numeric($submissionBoxId)) {
    error(2);
}

// compile the list of students over which we will iterate
try {
    $submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
    $submissionBox = $submissionBoxMapper->findById($submissionBoxId);
}
catch (\Schoolbox\Mapper\Exception\Deleted $e) {
    error(17);
}

$studentRetHandler = new SubmissionReturn();
$studentRetHandler->securityCheck($submissionBoxId);

// we log the viewing of this page, I know not why
$submissionHandler = new Submission();
$submissionHandler->logPageView(3, $submissionBoxId);

/* Add category codes
 */
$categoryCodeMapper = new \Schoolbox\Resource\CategoryCodeMapper();
$categoryCode = $categoryCodeMapper->findForCategory($submissionBox->getCategory()->getId());
$submissionBox->getCategory()->setCodes($categoryCode);

$studentList = $submissionHandler->getAllParticipantsForSubmission($submissionBox);

$dueWorkDetail = new DueWork_Detail($submissionBox);
$dueWorkDetail->processStudents($studentList);
$dueWorkDetail->renderDetails($this);

