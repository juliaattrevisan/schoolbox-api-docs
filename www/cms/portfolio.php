<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-folder.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-file.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';

$this->setFile(TEMPLATE_content, 'cms/portfolio.html');
$this->setBlock(TEMPLATE_content, 'category_list', 'category_list_block');
$this->setBlock(TEMPLATE_content, 'file_list', 'file_list_block');
$this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');

$this->setTitle('My Files');
$this->setTab('myfiles');


$groupHandler = new Group;
$allowedFiles = new AllowedFile;

unset($_SESSION['portfolioID']);
if (isset($this->arg[0])) {
    $_SESSION['portfolioID'] = $this->arg[0];
    SitePage::localRedir('/cms/portfolio/');
} else {
    $openFolder = $_SESSION['portfolioID'];
}

$folderHandler = new MyFolder;
$fileHandler = new MyFile;

$openFolder = (int)$openFolder;

// get parentIDs from openFolder to 0
$parentList[] = $parentID = $openFolder;
while ($parentID > 0) {
    $parentList[] = $parentID = $folderHandler->getParent($parentID);
}


// output folders, column 1
$folderList = $folderHandler->getFolderNode($openFolder);
if (!is_array($folderList)) {
    $parentList = array(0);
    $openFolder = 0;
    $folderList = $folderHandler->getFolderNode($openFolder);
}

if (is_array($folderList)) {
    foreach ($folderList as $folder) {

        $folder['children'] = $folderHandler->hasChildren($folder['id']);

        $class = array();

        // set parent folder
        $class[] = "child-of-node-{$folder['parent']}";

        // has children
        if ($folder['children'] > 0) {

            // is currently expanded
            if (in_array($folder['id'], $parentList)) {
                $class[] = "populated expanded";
            } else {
                $class[] = "collapsed";
            }

        }

        // has files showing
        $folder["id"] == $openFolder ? $class[] = "opened selected" : null;

        // write access
        $class[] = "write";

        $output = array(
            'CATEGORY_ID' => $folder['id'],
            'CATEGORY_NAME' => Scrub::text($folder['name']),
            'CLASS' => implode(" ", $class),
        );
        $this->setVar($output);
        $this->parse('category_list_block', 'category_list', true);
    }
} else {
    $this->setVar('category_list_block', '');
}


$fileList = $fileHandler->getAllInFolder($openFolder);
if (is_array($fileList) && count($fileList) > 0) {

    foreach ($fileList as $file) {

        $class = array();

        // icon
        $class[] = $allowedFiles->getClass(!empty($file['iconFile']) ? $file["iconFile"] : "txt.png");

        /* Note that WebDAV returns the $file['submitted'] value in a different format
           * than Schoolbox.
           */
        $modifiedTime = new Alaress_Date_Format(strtotime($file['submitted']), 'U');

        $output = array(
            'FILE_ID' => $file['id'],
            'TYPE' => 'file',
            'FILE_NAME' => Scrub::text($file['description']),
            'FILE_MODIFIED' => $modifiedTime->getShort(),
            'FILE_SIZE' => $file['file_size'] > 0 ? Alaress_Byte_Format::bytesToString($file['file_size']) : "--",
            'FOLDER_ID' => $file['parent'],
            'CLASS' => implode(" ", $class),
            'PUBLISHED' => $file['published'] != '' ? $file['published'] : '',
        );

        $this->setVar($output);
        $this->parse('file_list_block', 'file_list', true);

    }
} else {
    $this->setVar(
        'file_list_block', '<tr class="category-' . $openFolder . ' empty"><td>No files to display</td></tr>'
    );
}

// output mime classes
$allowedList = $allowedFiles->getAll();
if (is_array($allowedList)) {

    $last = '';
    foreach ($allowedList as $allowed) {
        if ($last != $allowed["iconFile"]) {
            $output = array(
                "NAME" => $allowedFiles->getClass($allowed["iconFile"]),
                "ICON" => $allowed["iconFile"],
            );

            $this->setVar($output);
            $this->parse("allowed_files_block", "allowed_files", true);

            $last = $allowed["iconFile"];
        }
    }
} else {
    $this->setVar('allowed_files_block', '');
}

