<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

use Schoolbox\UserMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;

$page = new SitePage;

if ( isset( $_FILES['file']['name'] ) && $_FILES['file']['name'] && is_numeric($_POST['box_id']) ) {
    $allowedFileHandler = new AllowedFile;
    $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));

    $submitDir = Cfg::get('submission_dir') . "/";
    if(!is_writable($submitDir)) {
        error(9, "unable to upload file to submission box.<br />Please try again, and if the problem persists please contact your teacher or system administrator.");
    }

    $user           = UserMapper::getInstance()->findById($_SESSION['id']);
    $submission_box = SubmissionBoxMapper::getInstance()->findById($_POST['box_id']);

    $submission = new Submission();
    $submission
        ->setSubmissionBox($submission_box)
        ->setOwner($user)
        ->setSubmitted(new Alaress_Date_Format(new DateTime('now'), 'U'))
        ->setFilename($_FILES['file']['name'])
        ->setTempLocation($_FILES['file']['tmp_name'])
        ->loadMimetypeFromFilename()
        ->loadIsLate()
    ;

    //
    // Check uploadability before saving
    $submission_mapper = new SubmissionMapper();
    try {
        $submission_mapper->checkUploadability($submission);
    }
    catch (Schoolbox\Resource\Exception $e) {
        error(17);
    }
    $submission_mapper->save($submission);
    $submissionId = $submission->getId();

    $plagscanApiKey = Config::get('plagscan_api_key');
    $plagscanUsername = Config::get('plagscan_username');

    /* Submit to PlagScan for plagiarism check */
    if ( !empty( $plagscanApiKey ) && !empty( $plagscanUsername ) ) {
        if ( is_numeric($submissionId) ) {
            $document = new Plagscan_Document();

            $document->setFileId($submissionId);

            $document->setFile(array(
                'id' => $submissionId,
                'name' => $_FILES['file']['name'],
                'file' => $submitDir . "$submissionId.dat"
            ));

            // argument[1] ('true') here sends the redirect header and closes the connection, but allows us to continue processing
            $page->localRedir('/submission/submissionHistory/'.$_POST['box_id'].'/'.$_SESSION['id'], true);

            Plagscan_DocumentMapper::getInstance()->save($document);

            exit;
        }
    }

    $page->localRedir('/submission/submissionHistory/'.$_POST['box_id'].'/'.$_SESSION['id']);
}
/* Exceeded PHP max file size */
elseif (upload_failed()) {
    error(10);
}
else {
    error(2);
}