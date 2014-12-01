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

$studentRetHandler = new SubmissionReturn();
$studentRetHandler->securityCheck($submissionBoxId);

// we log the viewing of this page, I know not why
$submissionHandler = new Submission();
$submissionHandler->logPageView(3, $submissionBoxId);

// compile the list of students over which we will iterate
$submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
$submissionBox = $submissionBoxMapper->findById($submissionBoxId);

/* Add category codes
 */
$categoryCodeMapper = new \Schoolbox\Resource\CategoryCodeMapper();
$categoryCode = $categoryCodeMapper->findForCategory($submissionBox->getCategory()->getId());
$submissionBox->getCategory()->setCodes($categoryCode);

$studentList = $submissionHandler->getAllStudentsForSubmission($submissionBox);

$dueWorkDetail = new DueWork_Detail($submissionBox->getId(), $submissionBox);
$dueWorkDetail->processStudents($studentList);
$dueWorkDetail->renderDetails($this);

