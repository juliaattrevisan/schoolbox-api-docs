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

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;
$box_mapper        = new SubmissionBoxMapper();
$submission_mapper = new SubmissionMapper();

//
// Submission mapper - will get the most recent submission for this student.
$user_id = $_SESSION['id'];
$box_id  = $this->arg[0];

//
// Should be some permissions on whether a user is a member of group

$submission_box = $box_mapper->findById($this->arg[0]);
$dueDate        = new Alaress_Date_Relative($submission_box->getDueDate(), 'y-m-d');
$createdDate    = new Alaress_Date_Relative($submission_box->getCreated(), 'y-m-d');
$urgency        = intval(($dueDate->getUnixTimestamp() - time()) / 86400);
$gradient       = max(0, min(10, $urgency));

//
// Find most recent submissions for this user - later, consider showing drafts
// across side of screen.
$drafts = $submission_mapper->getDrafts($user_id, $box_id, 10);
$body   = '';
if (count($drafts)) {
	foreach ($drafts as $draft) {
		$body = $draft->getBody();
		break;
	}
}

$this->setVar(array(
    'CATEGORY_NAME' => Scrub::text($submission_box->getCategory()->getName()),

    'BOX_DUEDATE'          => $dueDate->getRelativeTime(),
    'BOX_DUEDATE_GRADIENT' => $gradient,

    'BOX_ID'        => $submission_box->getId(),
    'SUBMISSION_ID' => null,
    'BOX_NAME'      => Scrub::text($submission_box->getName()),
    'BOX_TYPE'      => $submission_box->getWorkType()->getName(),
    'BOX_CREATED'   => $createdDate->getRelativeTime(),
    'TEXTBOX_BODY'  => ($draft instanceof Submission ? Scrub::text($draft->getBody()) : ''),
));
