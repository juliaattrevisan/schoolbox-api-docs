<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionStudent.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionReturn.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';

$page = new SitePage;

if( isset($_FILES['file']['name']) && $_FILES['file']['name'] && is_numeric($_POST['box_id']))
{

        $allowedFileHandler = new AllowedFile;
        $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));

        $studHandler = new SubmissionStudent;
        $studHandler->create($_POST['box_id'], $_FILES['file']);
	
        $page->backToTheFuture(2);
}
else
    error(2);

?>
