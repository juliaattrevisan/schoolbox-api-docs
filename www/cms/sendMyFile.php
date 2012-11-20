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
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-file.php';

if (is_numeric($_GET['id'])) {
    $page = new SitePage;

    $myFileHandler = new MyFile;
    $file = $myFileHandler->getByID($_GET['id']);
    if (!is_array($file))
        error(41);

    if ($file['published']) {
        $eportfolioHandler = new Eportfolio_Container($file['owner']);

        if (!$eportfolioHandler->canEdit($file['owner'])) {
            error(5);
        }
    } else {
        if ($file['owner'] != $_SESSION['id'])
            error(5);
    }

    $filename = Cfg::get('myfile_dir') . "/" . $file['id'] . ".dat";

    if ($_GET['save'])
        $attach = "attachment;";
    else
        $attach = "";

    $filesize = @filesize($filename);
    if ($filesize == FALSE) //if we cannot find the file (something is wrong dont link to it)
        error(41);

    //send headers
    if ($file['mime_type'])
        header("Content-Type: {$file['mime_type']}");
    else
        header("Content-Type: application/octet-stream");

    header("Pragma: ");
    header("Cache-control: ");
    header("Content-Disposition: $attach filename=\"" . $file['realname'] . "\"");
    header("Content-Length: $filesize");
    readfile($filename);
} else
    error(2);

