<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionReturn.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';

$page = new SitePage;

if( ((isset($_POST['comment']) && $_POST['comment']) || (isset($_POST['mark']) && $_POST['mark']) || (isset($_FILES['file']['name']) &&
	$_FILES['file']['name'])) && (isset($_POST['sid']) && $_POST['sid']) && (isset($_POST['box_id']) && $_POST['box_id']))
{

        $returnHandler = new SubmissionReturn;
        $returnHandler->securityCheck($_POST['box_id'], $_POST['sid']);


        if(isset($_FILES['file']['name'])) {
            $allowedFileHandler = new AllowedFile;
            $allowedFileHandler->fileSecurityCheck($_FILES['file']);
        }

        $return = array('box_id'    => $_POST['box_id'],
                        'owner'     => $_POST['sid'],
                        'comment'   => $_POST['comment'],
                        'mark'      => $_POST['mark'],
                        );

        $returnHandler->create($return, $_FILES['file']);

        $page->backToTheFuture(2);
}
else
    error(2);

?>
