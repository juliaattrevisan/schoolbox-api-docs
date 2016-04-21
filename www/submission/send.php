<?php
/************************************************************
*                                                           *
*         Copyright (c) 2013 Alaress Pty Ltd                *
*                                                           *
************************************************************/
//this script opens files for download and send them to the user with correct filename

use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;

//includes
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';
$groupHandler = new Group;

if (!is_numeric($_GET['id'])) {
    error(2);
    exit;
}

$file     = null;
$filename = null;
$content  = null;
$size     = null;
$mime     = null;

$page     = new SitePage;
$attach   = $_GET['save'] ? 'attachment;' : '';

if (is_numeric($_GET['return'])) {
    $mapper = new ReturnedWorkMapper();

    try {
        $return = $mapper->findById($_GET['id']);

        // hydrate
        $file = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($return->getStorage()->getHash());
        $return->setStorage($file);

    } catch (Schoolbox\Mapper\Exception $e) {
        error(44);
        exit;
    }

    if (!$return->hasPermission($_SESSION['user'])) {
        error(44);
    }

    // does this file exist, and is it readable?
    if (!$file->exists()) {
        error(9, "the requested file could not be read.<br />Please try again, and if the problem persists contact your system administrator.");
    }

    $size     = $return->getSize();
    $filename = $return->getBasename();
    $mime     = $return->getMime();

} else {
    $mapper = new FileMapper();

    try {
        $studentSub = $mapper->findById($_GET['id']);

        // Now lazy model is gone, need to load submission box and owner manually
        $studentSub->setSubmissionBox(
            SubmissionBoxMapper::getInstance()->findById(
                $studentSub->getSubmissionBox()->getId()
            )
        );
        $studentSub->setOwner(
            Schoolbox\UserMapper::getInstance()->findById(
                $studentSub->getOwner()->getId()
            )
        );

    } catch (Schoolbox\Mapper\Exception $e) {
        error(44);
        exit;
    }

    if (!$studentSub->hasPermission(
        $_SESSION['user'],
        \Schoolbox\Permission\Access\UserAccessMapper::getInstance(),
        \Eportfolio\ShowcaseMapper::getInstance()
    )) {
        error(44);
    }

    // filename format to be {lastname-firstname_month-day_hour-minute_{sanitised(filename)}}
    // eg. Day-Matt_0816-1432_MyAssignment.docx (from 'My Assign!ment.docx')
    $filename = sprintf("%s-%s_%s_%s",
        $studentSub->getOwner()->getLastName(),
        $studentSub->getOwner()->getFirstName(),
        $studentSub->getCreatedAt()->getUsingFormat('Ymd-Hi'),
        $studentSub->getBasename()
    );
    // sanitise submission filename only
    $filename = preg_replace("#[^a-z0-9_\\-\\(\\)\\.]#i", "", $filename);

    $file = $studentSub->getStorage();

    // does this file exist, and is it readable?
    if (!$file->exists()) {
        error(9, "the requested file could not be read.<br/>Please try again, and if the problem persists contact your system administrator.");
    }

    $mime = $file->getMime();
    $size = $file->getSize();

    // wrap HTML in niceness
    if ($studentSub->getMimetype()->matches('text/html')) {

        $wrapper = new Alaress\Html\Wrapper($filename, $file->read());
        $wrapper->loadBaseHrefFromServerVars($_SERVER);

        $content = $wrapper->render();
        $size    = strlen($content);
        $attach  = '';

        if ($size === 0) {
            error(9, "the requested file could not be read.<br/>Please try again, and if the problem persists contact your system administrator.");
        }
    }
}

//send headers (cache-control off stops IE8 from failing to download)
header ("Pragma: ");
header ("Cache-control: ");
header ("Content-Type: $mime");
header ("Content-Disposition: $attach filename=\"".$filename."\"");

// Scrub it, if option is specified in the querystring
if ($_GET['scrub'] == 1) {
    $content = Scrub::html($content ?: $file->read());
}

if ($content) {
    header("Content-Length: $size");
    die($content);
}

header("Content-Length: $size");
fpassthru($file->readStream());

// just in case
exit;
