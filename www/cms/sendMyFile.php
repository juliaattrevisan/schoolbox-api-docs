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
//
//	if (!is_array($file)) {
//        error(41);
//	}

	/* Check eportfolio permissions
	 */
	$mapper = new \Component\Eportfolio\FileListMapper();

	$allFiles = $mapper->findByColumn('file_id', $file['id']);

	$isViewable = false;

	if ($allFiles->count()) {
		foreach ($allFiles as $currentFile) {
			$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $file['owner'], $currentFile->getPageId());

			/* If you can view one of the pages that this file is shared on,
			 * break out of here
			 */
			if ($authenticator->canView()) {
				$isViewable = true;

				break;
			}
		}
	}

	/* If the file was never viewable on any eportfolio page and you aren't
	 * the owner, get out
	 */
	if (!$isViewable && ($_SESSION['id'] != $file['owner'])) {
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

