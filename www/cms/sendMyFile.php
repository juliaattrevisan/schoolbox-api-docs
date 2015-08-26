<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 ************************************************************/
//this script opens files for download and send them to the user with correct filename
//future enhancements could include storing the content type to help browser file recognition
//rather than just octet stream

//includes
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

if (is_numeric($_GET['id'])) {
    // ensure session
    new SitePage;

    $fileMapper = Schoolbox\Resource\My\File\Mapper::getInstance();

    try {
        $file = $fileMapper->findById($_GET['id']);
    } catch (Schoolbox\Mapper\Exception\Deleted $e) {
        error(41);
        exit;
    } catch (Schoolbox\Mapper\Exception $e) {
        error(41);
        exit;
    }

	/* Check eportfolio permissions
	 */
	$fileListMapper = Component\Eportfolio\FileListMapper::getInstance();

	$allFiles = $fileListMapper->findByColumn('file_id', $file->getId());

	$isViewable = false;

	if ($allFiles->count()) {
		foreach ($allFiles as $currentFile) {
			$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $file->getOwner()->getId(), $currentFile->getPageId());

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
	if (!$isViewable && ($_SESSION['user']->getId() != $file->getOwner()->getId()) && !$_SESSION['user']->isSuperuser()) {
		error(5);
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
} else
    error(2);

