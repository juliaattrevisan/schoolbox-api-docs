<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

$page = new SitePage;
$allowedFiles = new AllowedFile;

if (isset($_POST['relationID'])) {
    // native folder node request

    $folderHandler = new MyFolder;
} else {
    // native files request

    $fileHandler = new MyFile;
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
}
elseif (is_numeric($_POST['categoryID'])) {

    // save current view
    $_SESSION['portfolioID'] = $_POST['categoryID'];

    $fileList = $fileHandler->getAllInFolder($_POST['categoryID']);

    $output = array();
    if (is_array($fileList)) {
        foreach ($fileList as $file) {
            $modifiedDate = new Alaress_Date_Format(strtotime($file['submitted']), 'U');

            $output[] = array('id' => $file['id'],
                'name' => Scrub::text($file['description']),
                'category' => $file['category'],
                'modified' => $modifiedDate->getShort(),
                'size' => $file['file_size'] > 0 ? Alaress_Byte_Format::bytesToString($file['file_size']) : "--",
                'icon' => $allowedFiles->getClass(!empty($file['iconFile']) ? $file["iconFile"] : "txt.png"),
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