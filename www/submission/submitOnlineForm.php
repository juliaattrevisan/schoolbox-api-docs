<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

use Schoolbox\UserMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;

$page = new SitePage();

$box_mapper        = new SubmissionBoxMapper();
$submission_mapper = new SubmissionMapper();
$user_mapper       = new UserMapper();

$user = $user_mapper->findById($_SESSION['id']);
if (!empty($_POST['submission_id'])) {
	$submission     = $submission_mapper->findById($_POST['submission_id']);
	$submission_box = $submission->getSubmissionBox();
}
else {
	$submission     = new Submission();
	$submission_box = $box_mapper->findById($_POST['box_id']);
	$submission
		->setSubmissionBox($submission_box)
		->setOwner($user)
	;
}

//
// Why wouldn't I be able to edit this item?
// * I'm not the student who owns this submission
// * I don't have permission to submit to this box
// * It's past the box's expiry
// * Hey, all this should be in SubmissionMapper->checkUploadablity
try {
	$submission_mapper->checkUploadability($submission);
}
catch (Schoolbox\Resource\Exception $e) {
	//
	// what's access denied error?
	error(17);
}

$is_draft = isset($_POST['save_draft']);
$submission
	->loadIsLate()
	->setSubmitted(new Alaress_Date_Format(new DateTime('now'), 'U'))
	->setBody($_POST['body'])
	->setDraft($is_draft)
;
//
// Set file columns to dummy values
$submission->setFilename('');
$submission_mapper->save($submission);

//
// Draft? don't send to Plagscan yet.
//if (!$is_draft) {
//
//	$plagscanApiKey   = Config::get('plagscan_api_key');
//	$plagscanUsername = Config::get('plagscan_username');
//
//	/* Submit to PlagScan for plagiarism check */
//	if (!empty($plagscanApiKey) && !empty($plagscanUsername)) {
//		if ( is_numeric($submissionId) ) {
//			$document = new Plagscan_Document();
//			$document->fileId = $submissionId;
//			$document->file = array(
//				'id' => $submissionId,
//				'name' => $_FILES['file']['name'],
//				'file' => $submitDir . "$submissionId.dat"
//			);
//			// argument[1] ('true') here sends the redirect header and closes the connection, but allows us to continue processing
//			$page->localRedir('/submission/submissionHistory/'.$_POST['box_id'].'/'.$_SESSION['id'], true);
//			Plagscan_DocumentMapper::getInstance()->save($document);
//			exit;
//		}
//	}
//}


$page->localRedir('/submission/submissionHistory/'.$_POST['box_id'].'/'.$_SESSION['id']);
