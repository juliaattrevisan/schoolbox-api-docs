<?php

use \Schoolbox\File\MimetypeMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

$folderHandler = PortfolioWebDAV::factory();
$fileHandler = $folderHandler;

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
}
elseif (is_numeric($_POST['categoryID'])) {

    // save current view
    $_SESSION['portfolioID'] = $_POST['categoryID'];

    $fileList = $fileHandler->getAllInFolder($_POST['categoryID']);

    $output = array();
    if (is_array($fileList)) {
        foreach ($fileList as $file) {
            $modifiedDate = new Alaress_Date_Format(strtotime($file['submitted']), 'U');

            try{
                $mime = MimetypeMapper::getInstance()->getByFilename($file['description']);
            } catch (Exception $e) {
                $mime = MimetypeMapper::getInstance()->getUnknownMime();
            }

            $output[] = array('id' => $file['id'],
                'name' => Scrub::text($file['description']),
                'category' => $file['category'],
                'modified' => $modifiedDate->getShort(),
                'size' => $file['file_size'] > 0 ? Alaress_Byte_Format::bytesToString($file['file_size']) : "--",
                'icon' => $mime->getClass(),
            );
        }
    }

    header("Content-type: application/json");
    print json_encode($output);
}
elseif (is_numeric($_POST['saveID'])) {
    // save current view
    $_SESSION['portfolioID'] = $_POST['saveID'];
}
