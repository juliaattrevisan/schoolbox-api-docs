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
elseif (isset($_POST['delete'])) {
	//
	// Which file was passed?
	$delete_id = $_POST['delete'];
	if (preg_match('/^old-(\\d+)$/', $delete_id, $matches)) {
		list(, $old_file_id) = $matches;
		//
		// Delete old file.
	}
	else {
		$file_mapper = new SubmissionFileMapper();
		$file_mapper->delete($file_mapper->findById($delete_id));
	}
}