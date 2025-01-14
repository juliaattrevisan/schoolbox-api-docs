<?php

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource;
use Schoolbox\Resource\Storage\FilesystemProxy;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$dropbox = Schoolbox\Dropbox\Mapper::getInstance();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$page->setAjax(true);

if (is_numeric($_GET['delete'] ?? false)) {
    try {
        $file = $dropbox->findById($_GET['delete']);
        $cid  = $file->getFolder()->getId();

        $dropbox->delete($file);
    } catch (Schoolbox\Mapper\Exception $e) {
        error(43);
        exit;
    }
    $page->localRedir("/homepage/$cid");
} elseif (is_numeric($_GET['id'] ?? false)) {
    try {
        $file = $dropbox->findById($_GET['id']);

        // TODO: replace with UAM once merged with SB-7185
        $g = new Group();
        $access = $g->getPermission($file->getFolder()->getId());
        if ($access < UserAccess::ACCESS_WRITE) {
            error(5);
        }
        if (!$dropbox->folderHasDropbox($file->getFolder())) {
            error(5);
        }

        if ($_GET['save'] ?? false) {
            $attach = "attachment;";
        } else {
            $attach = "";
        }

        /** @var FilesystemProxy $filesystemProxy */
        $filesystemProxy = $kernel->get('filesystem.storageProxy');

        //send headers
        header("Cache-control: private");
        header("Content-Ranges: none");
        header("Content-Type: {$file->getStorage()->getMimetype()->getMime()}");
        header("Content-Disposition: $attach filename=\"" . $file->getStorage()->getBasename() . "\"");
        header("Content-Length: " . $filesystemProxy->fileSize($file->getStorage()));
        fpassthru($filesystemProxy->readStream($file->getStorage()));
        exit;
    } catch (Schoolbox\Mapper\Exception $e) {
        error(5);
        exit;
    }
} elseif (is_numeric($_POST['category']) && $_POST['description'] && is_array($_POST['attachment'])) {
    // TODO replace with UAM once merged with SB-7185
    $g = new Group();
    $access = $g->getPermission($_POST['category']);
    $folder = Resource\Folder\Mapper::getInstance()->createModel()->setId($_POST['category']);
    if ($access < UserAccess::ACCESS_READ) {
        error(5);
    }
    if (!$dropbox->folderHasDropbox($folder)) {
        error(5);
    }

    $factory = new \Schoolbox\Resource\Attachment\Collector\CollectorFactory();

    try {
        /** @var Resource\Storage\Model $storage */
        $storage = $factory->build($_POST['attachment'])->getFirst();

        $file = $dropbox->createModel()
                        ->setOwner($_SESSION['user'])
                        ->setStorage($storage)
                        ->setTitle($_POST['description'])
                        ->setFolder($folder)
                        ->setFilename($storage->getHash() . '.' . $storage->getExtension());

        $dropbox->create($file);

        $_SESSION['dropSuccess'] = 1;
    } catch (\Schoolbox\Resource\Exception $e) {
        error(43);
        exit;
    } catch (InvalidArgumentException $e) {
        error(43);
        exit;
    }

    SitePage::localRedir("/homepage/" . $_POST['category']);
} else {
    error(2);
}
