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

if (is_numeric($_GET['id'])) {
    $page = new SitePage;
    $groupHandler = new Group;

    if (isset($_GET['folder'])) {

        if ($groupHandler->getPermission($_GET['id']) < READ_ACCESS)
            error(5);



        $config = array(
            'sort' => 'Date',
            'sortdir' => 'DESC',
            'size' => 1,
        );

        $fileHandler = new File;
        $file = $fileHandler->getAllInCategory($_GET['id']);
        $file = $file[0];

        if (!is_array($file))
            error(41);

        $filename = Cfg::get('file_dir') . "/" . $file['id'] . ".dat";
    }
    else {

        $fileHandler = new File;
        $file = $fileHandler->getByID($_GET['id']);
        if (!is_array($file))
            error(41);

        if ($groupHandler->getPermission($file['category']) < READ_ACCESS)
            error(5);

        $filename = Cfg::get('file_dir') . "/" . $_GET['id'] . ".dat";

        $fileHandler->logAction($_GET['id'], 'access');

    }

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

