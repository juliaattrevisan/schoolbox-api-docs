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
		->setFilename('Online Submission')
		->setSubmissionBox($submission_box)
		->setOwner($user)
		->setMimetype('text/html')
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
$body     = $_POST['body'];
$is_draft = isset($_POST['save_draft']);
$submission
	->loadIsLate()
	->setSubmitted(new Alaress_Date_Format(new DateTime('now'), 'U'))
	->setBody($body)
	->setDraft($is_draft)
;
$submission_mapper->save($submission);

//
// Draft? don't send to Plagscan yet.
if (!$is_draft) {
	$plagscanApiKey   = Config::get('plagscan_api_key');
	$plagscanUsername = Config::get('plagscan_username');
	$submission_id    = $submission->getId();

	if (!empty($plagscanApiKey) && !empty($plagscanUsername) && !empty($submission_id)) {
		$document = new Plagscan_Document();
		$document->setFileId($submission_id);

		//
		// Make a temp file for Plagscan to work with.
		// Molest the filename so that Plagscan always accepts the document as
		// scannable.
		$tempfile = tempnam(sys_get_temp_dir(), 'sb_pgs');
		file_put_contents($tempfile, $body);
		$document->setFile(array(
			'id'   => $submission_id,
			'name' => $submission->getFilename().'.html',
			'file' => $tempfile,
		));

		// argument[1] ('true') here sends the redirect header and closes the connection, but allows us to continue processing
		$page->localRedir('/submission/submissionHistory/'.$_POST['box_id'].'/'.$_SESSION['id'], true);
		Plagscan_DocumentMapper::getInstance()->save($document);
		exit;
	}
}


$page->localRedir('/submission/submissionHistory/'.$_POST['box_id'].'/'.$_SESSION['id']);
