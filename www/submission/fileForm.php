<?php

//
// Upload destination for files submitted when creating a submission.
require_once( dirname(__FILE__) . '/../../include/Bootstrap.php' );

//
// Force session usage due to flash uploader
// http://www.uploadify.com/documentation/uploadify/using-sessions-with-uploadify/
if (isset($_POST[session_name()])) {
	session_id($_POST[session_name()]);
}
if (isset($_GET[session_name()])) {
	session_id($_GET[session_name()]);
}
session_start();

//
// Submission doesn't exist yet?
// Save the files in storage, and when the submission is saved save them as part
// of what was uploaded.
use Schoolbox\File;
use Schoolbox\FileMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\FileMapper as SubmissionFileMapper;

$return = array(
	'success' => false,
	'message' => '',
);
if (isset($_FILES['file'])) {
	$return['action']  = 'upload';
	$return['name']    = $_FILES['file']['name'];
	$return['file_id'] = null;
	//
	// TODO move the file security checking out of handlers!
	// (Though it's not the place of the submission box or file uploader to
	//  check either.)
	$allowed_file_handler = new AllowedFile();
	$allowed_file_check   = $allowed_file_handler->fileSecurityCheck($_FILES['file']);
	if (!empty($allowed_file_check)) {
		$return['message'] = $allowed_file_check['message'];
	}
	else {
		$file = new File();
		$file->setFilename($_FILES['file']['name']);
		$file->setTempLocation($_FILES['file']['tmp_name']);
		$file->setUploadedBy($_SESSION['id']);
		$file->loadHashFromTempLocation();
		$file->loadMimetypeFromFilename();
		$file->loadFilesizeFromTempLocation();

		//
		// Does a file with this hash exist already? If so use it, otherwise create a
		// new one.
		$file_id     = null;
		$file_mapper = new FileMapper();
		$existing    = $file_mapper->findByColumn('hash', $file->getHash());
		if ($existing->count()) {
			$file_id = $existing->current()->getId();
		}
		else {
			$file_mapper->save($file);
			$file_id = $file->getId();
		}
		$return['success'] = !empty($file_id);
		$return['file_id'] = $file_id;
	}
}
elseif (isset($_GET['delete'])) {
	$file_id = $_POST['file_id'];
	$box_id  = $_POST['box_id'];

	$return['action']  = 'delete';
	$return['file_id'] = $file_id;
	$return['box_id']  = $box_id;
	$mapper  = new SubmissionBoxMapper();

	//
	// Permission check - can this user change this box?
	$submission    = $mapper->findById($box_id);
	$group_handler = new Group();
	if (!$submission) {
		$return['message'] = 'Not Found';
	}
	elseif ($group_handler->getPermission($submission->getCategory()) < WRITE_ACCESS) {
		$return['message'] = 'Unauthorised';
	}
	else {
		$result = false;
		if (!empty($file_id) && !empty($box_id)) {
			if ($file_id === 'old') {
				$submission->setLegacyFileId(null);
				$result = $mapper->save($submission);
			}
			else {
				$file = $submission->getFileLinks()->getBy('fileId', $file_id);
				if ($file) {
					$file_mapper = new SubmissionFileMapper();
					$result = $file_mapper->delete($file);
				}
			}
		}
		$return['success'] = $result ? true : false;
	}
}
echo json_encode($return);
