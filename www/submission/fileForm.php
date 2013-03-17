<?php

//
// Upload destination for files submitted when creating a submission.
// TODO add authorisation and file check
// $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));
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
//use Schoolbox\Assessment\Component\SubmissionBox\File       as SubmissionFile;
use Schoolbox\Assessment\Component\SubmissionBox\FileMapper as SubmissionFileMapper;

if (isset($_FILES['file'])) {
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

	//
	// Spit out the file ID (or hash)
	echo json_encode(array('file_id' => $file_id, 'hash' => $file->getHash()));
}
elseif (isset($_GET['delete'])) {
	//
	// Which file was passed?
	$mapper  = new SubmissionBoxMapper();
	$file_id = $_POST['file_id'];
	$box_id  = $_POST['box_id'];
	$result  = false;
	if (!empty($file_id) && !empty($box_id)) {
		$submission = $mapper->findById($box_id);
		if ($submission) {
			if ($file_id === 'old') {
				$submission->setLegacyFileId(0);
				$result = $mapper->save($submission) ? true : false;
			}
			else {
				$file = $submission->getFileLinks()->getBy('fileId', $file_id);
				if ($file) {
					$file_mapper = new SubmissionFileMapper();
					$result = $file_mapper->delete($file);
				}
			}
		}
	}
	echo json_encode(array('file_id' => $file_id, 'box_id' => $box_id, 'deleted' => $result));
}