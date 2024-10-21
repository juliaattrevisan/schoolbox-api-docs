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
require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->setupSentryTrace(__FILE__);

if (is_numeric($_GET['id']) && Config::get('webdav_enable')) {
    /** @var SitePage $page */
    $page = $kernel->get('sitePage');
    $page->initSession();
    $groupHandler = new Group();
    $fileHandler  = PortfolioWebDAV::factory();
    try {
        $filename = $fileHandler->getFile($_GET['id']);
    } catch (Sabre\DAV\Exception $ex) {
        error(41);
    }
    $file['realname'] = substr($_SESSION['portfolioIdPathMap'][$_GET['id']], strrpos($_SESSION['portfolioIdPathMap'][$_GET['id']], '/') + 1);

    if ($_GET['save'] ?? false) {
        $attach = "attachment;";
    } else {
        $attach = "";
    }

    $filesize = filesize($filename);
    if ($filesize == false) { //if we cannot find the file (something is wrong dont link to it)
        unlink($filename);
        error(41);
    }

    //send headers
    if ($file['mime_type'] ?? false) {
        header("Content-Type: {$file['mime_type']}");
    } else {
        header("Content-Type: application/octet-stream");
    }

    header("Pragma: ");
    header("Cache-control: private");
    header("Content-Ranges: none");
    header("Content-Disposition: $attach filename=\"" . $file['realname'] . "\"");
    header("Content-Length: $filesize");
    readfile($filename);
    unlink($filename);
} else {
    error(2);
}
