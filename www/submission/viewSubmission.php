<?php

if (!isset($this)) {
    require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
    SitePage::localRedir('/submission/viewSubmission');
}

// check the ID is valid
if (!is_numeric($this->arg[0])) {
    error(2);
}

$submissionBoxID = $this->arg[0];

$submissionHandler = new Submission();
$studentRetHandler = new SubmissionReturn();


$subBox = $submissionHandler->getByID($this->arg[0]);
$studentRetHandler->securityCheck($this->arg[0]);

// we log the viewing of this page, I know not why
$submissionHandler->logPageView(3, $this->arg[0]);

// compile the list of students over which we will iterate
$studentList = $submissionHandler->getAllStudentsForSubmission($subBox);

$dueWorkDetail = new DueWork_Detail($submissionBoxID);
$dueWorkDetail->processStudents($studentList);
$dueWorkDetail->renderDetails($this);

