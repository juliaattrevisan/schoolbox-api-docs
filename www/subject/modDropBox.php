<?php

require_once '../../include/Bootstrap.php';

$dropbox = new Dropbox();
$page    = new SitePage();
$page->setAjax(true);

if (is_numeric($_GET['delete'])) {
    $cid = $dropbox->deleteFile($_GET['delete']);
    $page->localRedir("/homepage/$cid");

} elseif (is_numeric($_GET['id'])) {
    $dropbox->outputFile($_GET['id'], $_GET['save']);

} elseif (is_numeric($_POST['category']) && $_POST['description'] && is_array($_POST['attachment'])) {
    // this is a bit roundabout, but this is really the only way to store this file without entirely rewriting the dropbox handler
    $factory = new \Schoolbox\Resource\Attachment\Collector\CollectorFactory();

    /** @var \Schoolbox\Storage $storage */
    $storage = $factory->build($_POST['attachment'])->getFirst();

    if ($fileId = $dropbox->addFile($storage->getFilename(), $storage->getMime(), $storage->getFileLocation(), $_POST['description'], $_POST['category'])) {
        $_SESSION['dropSuccess'] = 1;
    }

    SitePage::localRedir("/homepage/".$_POST['category']);

} else {
    error(2);

}
