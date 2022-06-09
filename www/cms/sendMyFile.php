<?php

/**
 * This script opens files for download and send them to the user with correct filename
 *
 * Future enhancements could include storing the content type to help browser file recognition
 * rather than just octet stream
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

//includes
use Schoolbox\Resource\Storage\FilesystemProxy;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

if (is_numeric($_GET['id'])) {
    // ensure session
    /** @var SitePage $page */
    $page = $kernel->get('sitePage');
    $page->initSession();

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
            $authenticator = new Eportfolio_Authenticator($_SESSION['user'], $file->getOwner(), $currentFile->getPageId());

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

    if (!$file->getMimetype()->getInline() || !empty($_GET['save'])) {
        $attach = "attachment;";
    } else {
        $attach = "";
    }

    /** @var FilesystemProxy $filesystemProxy */
    $filesystemProxy = $kernel->get('filesystem.storageProxy');

    //send headers
    header("Content-Type: {$file->getMime()}");
    header("Pragma: ");
    header("Cache-control: private");
    header("Content-Ranges: none");
    header("Content-Disposition: $attach filename=\"" . $file->getBasename() . "\"");
    header("Content-Length: {$file->getSize()}");
    fpassthru($filesystemProxy->readStream($file));
    exit;
} else {
    error(2);
}
