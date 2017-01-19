<?php

/*
 *
 * redirect to Redirection to 16.5 url
 *
 */
$url = '/learning/assessments/';
if (isset($this->arg[0]) && is_numeric($this->arg[0])) {
    $submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
    try {
        $submissionBox = $submissionBoxMapper->findById($this->arg[0]);
    }
    catch(\Exception $e) {
        $this->pageNotFound();
    }
    $assessmentId = $submissionBox->getAssessmentId();
    $url .= $assessmentId.'/status'; //assessmentId/status
    $this->localRedir($url, null, 301);
}
else {
    $this->pageNotFound();
}

/*
*Old code
*/

if (!isset($this)) {
    require_once dirname(__FILE__) . '/../../library/Bootstrap.php';
    SitePage::localRedir('/submission/viewSubmission');
}

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

$submissionBoxId = $this->arg[0];

// check the ID is valid
if (!is_numeric($submissionBoxId)) {
    error(2);
    exit();
}

// compile the list of students over which we will iterate
try {
    $submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
    $submissionBox = $submissionBoxMapper->findById($submissionBoxId);
}
catch (\Schoolbox\Mapper\Exception\Deleted $e) {
    error(17);
    exit();
}

$studentRetHandler = new SubmissionReturn();

if (!$submissionBox->hasAccess($sessionUser, \Schoolbox\Permission\Access\UserAccess::ACCESS_WRITE)) {
    error(25);
    exit();
}

// we log the viewing of this page, I know not why
$submissionHandler = new Submission();
$submissionHandler->logPageView(3, $submissionBoxId);

/* Add category codes
 */
$categoryCodeMapper = new \Schoolbox\Resource\Folder\Code\Mapper();
$categoryCode = $categoryCodeMapper->findForCategory($submissionBox->getCategory()->getId());
$submissionBox->getCategory()->setCodes($categoryCode);

$studentList = $submissionHandler->getAllParticipantsForSubmission($submissionBox);

$dueWorkDetail = new DueWork_Detail($submissionBox);
$dueWorkDetail->processStudents($studentList);
$dueWorkDetail->renderDetails($this);

