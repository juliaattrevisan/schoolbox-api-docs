<?php


$this->setFile(TEMPLATE_content, 'cms/portfolio.html');
$this->setBlock(TEMPLATE_content, 'file_list', 'file_list_block');
$this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');

$this->setTitle('My Files');
$this->setTab('myfiles');


$groupHandler = new Group;
$allowedFiles = new AllowedFile;

if (isset($this->arg[0])) {
    $_SESSION['portfolioID'] = $this->arg[0];
    SitePage::localRedir('/cms/portfolio/');
} else {
    $openFolder = $_SESSION['portfolioID'];
}

//$folderHandler = new MyFolder;
$fileHandler = Schoolbox\Resource\My\File\Mapper::getInstance();
$openFolder  = (int)$openFolder;

// Build a tree up to the last resource we looked at
$partialTree = array();
if ($_SESSION['user'] instanceof Schoolbox\User) {
    $treeBuilder = new Schoolbox\MyFolderTreeBuilder(
        Schoolbox\MyFolderMapper::getInstance(),
        $_SESSION['user']
    );
    $partialTree = $treeBuilder->buildPartialTree($openFolder);
}

// output folders, column 1 - now done via jsTree and TREEDATA
$this->setVar(
    array(
        'TREEDATA'      => json_encode($partialTree),
        'OPEN_CATEGORY' => intval($openFolder),
    )
);

$fileList = $fileHandler->findByFolderId($openFolder);
if ($fileList->count()) {

    foreach ($fileList as $file) {

        $class = array();

        // icon
        $class[] = $allowedFiles->getClass($file->getMimetype()->getIconFile());

        $output = array(
            'FILE_ID' => $file->getId(),
            'TYPE' => 'file',
            'FILE_NAME' => Scrub::text($file->getTitle()),
            'FILE_MODIFIED' => $file->getCreatedAt()->getShort(),
            'FILE_SIZE' => Alaress_Byte_Format::bytesToString($file->getSize()),
            'FOLDER_ID' => intval($file->getFolder()->getId()),
            'CLASS' => implode(" ", $class),
            'PUBLISHED' => $file->isPublished() ? $file->getPublishedAt()->getShort() : '',
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

