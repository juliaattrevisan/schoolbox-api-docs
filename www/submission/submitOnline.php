<?php

// not in sitepage context, redirect
if(!isset($this)) {
    require_once(dirname(__FILE__). '/../../include/Bootstrap.php');
    SitePage::localRedir('/submission/submissionHistory');
}

$this->setFile(TEMPLATE_content, 'submission/submitOnline.html');
$this->setTab('submission');


//
// wat do on this page?
// Show a text entry, that's about it.
// If the submission is closed, then show the submitted version instead,
// otherwise show the most recent edited version.
// Same sort of permission as on edit?
// allow teachers to mark this?

use Schoolbox\Assessment\Component;
$mapper = new Component\SubmissionBoxMapper();

//
// Submission mapper - will get the most recent submission for this student.
$user_id = $_SESSION['id'];
$box_id  = $this->arg[0];

//
// Should be some permissions on whether a user is a member of group

//$userHandler       = new User;
//$groupHandler      = new Group;
//$submissionHandler = new Submission;
//$returnHandler     = new SubmissionReturn;
//$allowedHandler    = new AllowedFile;

//$user = $userHandler->getByID($user_id);

$submission  = $mapper->findById($this->arg[0]);
$dueDate     = new Alaress_Date_Relative($submission->getDueDate(), 'y-m-d');
$createdDate = new Alaress_Date_Relative($submission->getCreated(), 'y-m-d');
$urgency     = intval(($dueDate->getUnixTimestamp() - time()) / 86400);
$gradient    = max(0, min(10, $urgency));

$this->setVar(array(
    'CATEGORY_NAME' => Scrub::text($submission->getCategory()->getName()),

    'BOX_DUEDATE'          => $dueDate->getRelativeTime(),
    'BOX_DUEDATE_GRADIENT' => $gradient,

    'BOX_ID'       => $submission->getId(),
    'BOX_NAME'     => Scrub::text($submission->getName()),
    'BOX_TYPE'     => $submission->getWorkType()->getName(),
    'BOX_CREATED'  => $createdDate->getRelativeTime(),
    'TEXTBOX_BODY' => '',
));
