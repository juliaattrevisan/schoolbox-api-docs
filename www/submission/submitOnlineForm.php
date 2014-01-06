<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;

$page = new SitePage();

$box_mapper        = new SubmissionBoxMapper();
$submission_mapper = new SubmissionMapper();
/** @var Schoolbox\User $user */
$user  = $_SESSION['user'];
$boxId = $_POST['box_id'];
if (!empty($_POST['submission_id'])) {
	$submission = $submission_mapper->findById($_POST['submission_id']);
	$boxId      = $submission->getSubmissionBox()->getId();
}
else {
	// Find a draft submission for this box and user - override it if possible.
	try {
		$submission = $submission_mapper->findOneByColumnList(
			array(
				'box_id' => $_POST['box_id'],
				'owner'  => $user->getId(),
				'draft'  => true,
			),
			array('submitted' => 'desc')
		);
		$boxId = $submission->getSubmissionBox()->getId();
	}
	catch (Schoolbox\Mapper\Exception $e) {
		$submission = new Submission();
		$submission
			->setFilename('Online Submission')
			->setMimetype('text/html')
		;
	}
	$submission->setOwner($user);
}
$submission->setSubmissionBox($box_mapper->findById($boxId));

//
// Why wouldn't I be able to edit this item?
// * I'm not the student who owns this submission
// * I don't have permission to submit to this box
// * It's past the box's expiry
// * Hey, all this should be in SubmissionMapper->checkUploadablity
$permission_denied = false;
try {
	$submission_mapper->checkUploadability($submission);
}
catch (Schoolbox\Resource\Exception $e) {
	//
	// what's access denied error?
	$permission_denied = true;
}
if (!$permission_denied) {
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
			if (!$page->detectAjax()) {
				$page->localRedir('/submission/submissionHistory/'.$_POST['box_id'].'/'.$_SESSION['id'], true);
			}
			Plagscan_DocumentMapper::getInstance()->save($document);
			exit;
		}
	}
}

if ($page->detectAjax()) {
	$result = array(
		'success' => false,
		'error'   => 500,
		'message' => 'Unknown Error',
	);
	if ($permission_denied) {
		$result['error']   = 403;
		$result['message'] = 'Permission Denied';
	}
	else {
		$result['success'] = true;
		$result['error']   = 200;
		$result['message'] = 'OK';
		$result['id']      = $submission->getId();
	}
	echo json_encode($result);
	exit;
}
else {
	$page->localRedir('/submission/submissionHistory/'.$_POST['box_id'].'/'.$_SESSION['id']);
}
