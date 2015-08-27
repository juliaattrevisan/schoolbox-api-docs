<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2002-2003 Lektec Consulting         *
 *                                                           *
 ************************************************************/
//this script opens files for download and send them to the user with correct filename
//future enhancements could include storing the content type to help browser file recognition
//rather than just octet stream

//includes
use Schoolbox\Resource\LogMapper;

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

if (is_numeric($_GET['id'])) {
    $page = new SitePage;
    $groupHandler = new Group;
    $fileHandler = false;
    $fileMapper = Schoolbox\Resource\File\Mapper::getInstance();

    if (isset($_GET['folder'])) {

        if ($groupHandler->getPermission($_GET['id']) < READ_ACCESS) {
            error(5);
        }

        $config = array(
            'sort' => 'date',
            'sortdir' => 'DESC',
            'size' => 1,
        );

        $file = $fileMapper->findForFolders([ $_GET['id'] ], $config)->first();

    } else {
        try {
            $file = $fileMapper->findById($_GET['id']);

            if ($groupHandler->getPermission($file->getFolder()->getId()) < READ_ACCESS) {
                error(5);
            }

            LogMapper::getInstance()->logRead($file, $_SESSION['user']);
        } catch (Schoolbox\Mapper\Exception $e) {
            error(41);
        }
    }

    if ($_GET['save'])
        $attach = "attachment;";
    else
        $attach = "";

    //send headers
    header("Content-Type: {$file->getMime()}");
    header("Pragma: ");
    header("Cache-control: ");
    header("Content-Disposition: $attach filename=\"" . $file->getBasename() . "\"");
    header("Content-Length: {$file->getSize()}");
    fpassthru($file->readStream());
    exit;
}
error(2);
