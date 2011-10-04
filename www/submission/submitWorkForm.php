<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$page = new SitePage;

if ( isset( $_FILES['file']['name'] ) && $_FILES['file']['name'] && is_numeric($_POST['box_id']) ) {
    $allowedFileHandler = new AllowedFile;
    $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));

    $studentSubmission = new SubmissionStudent;
    $submissionId = $studentSubmission->create($_POST['box_id'], $_FILES['file']);

    $plagscanApiKey = Config::get('plagscan_api_key');
    $plagscanUsername = Config::get('plagscan_username');

    /* Submit to PlagScan for plagiarism check */
    if ( !empty( $plagscanApiKey ) && !empty( $plagscanUsername ) ) {
        if ( is_numeric($submissionId) ) {
            $document = new Plagscan_Document();

            $document->fileId = $submissionId;

            $document->file = array(
                'id' => $submissionId, 'name' => $_FILES['file']['name'],
                'file' => Cfg::get('submission_dir') . "/$submissionId.dat"
            );

            Plagscan_DocumentMapper::getInstance()->save($document);
        }
    }

    $page->backToTheFuture(2);
}
/* Exceeded PHP max file size */
elseif ( $_SERVER['REQUEST_METHOD'] == 'POST' && $_SERVER['CONTENT_LENGTH'] > 0 && empty($_POST) && empty($_FILES) ) {
    error(10);
}
else {
    error(2);
}