<?php

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$allowedFiles = new AllowedFile();

if (isset($_POST['relationID'])) {
    // native folder node request

    $folderHandler = new MyFolder();
} else {
    // native files request

    $fileHandler = Schoolbox\Resource\My\File\Mapper::getInstance();
}


if (is_numeric($_POST['relationID'])) {
    $folderList = $folderHandler->getFolderList($_POST['relationID']);
    $output = array();
    if (is_array($folderList)) {
        foreach ($folderList as $folder) {
            $children = (int)$folderHandler->hasChildren($folder['id']);
            $output[] = array('id' => $folder['id'],
                'name' => Scrub::text($folder['name']),
                'parent' => $folder['parent'],
                'isParent' => $children ? 1 : 0,
            );
        }
    }
    header("Content-type: application/json");
    print json_encode($output);
} elseif (is_numeric($_POST['categoryID'])) {
    // save current view
    $_SESSION['portfolioID'] = $_POST['categoryID'];

    $fileList = $fileHandler->findByFolderId($_POST['categoryID']);

    $output = array();
    if ($fileList->count()) {
        foreach ($fileList as $file) {
            $output[] = array('id' => $file->getId(),
                'name' => Scrub::text($file->getTitle()),
                'category' => $file->getFolder()->getId(),
                'modified' => $file->getCreatedAt()->getShort(),
                'size' => Alaress_Byte_Format::bytesToString($file->getSize()),
                'icon' => $allowedFiles->getClass($file->getMimetype()->getIconFile()),
            );
        }
    }

    header("Content-type: application/json");
    print json_encode($output);
} elseif (is_numeric($_POST['saveID'])) {
    // save current view
    $_SESSION['portfolioID'] = $_POST['saveID'];
}
