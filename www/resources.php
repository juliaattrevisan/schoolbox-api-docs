<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Course\TaskMapper;

$this->setFile(TEMPLATE_content, 'resources.html');
$this->setBlock(TEMPLATE_content, 'file_list', 'file_list_block');
$this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');

// 3rd-panel blocks
$this->setBlock(TEMPLATE_content, 'folder_detail', 'folder_detail_block');
$this->setBlock('folder_detail', 'folder_detail_perms', 'folder_detail_perms_block');
$this->setBlock(TEMPLATE_content, 'file_detail', 'file_detail_block');
$this->setBlock(TEMPLATE_content, 'multi_file_detail', 'multi_file_detail_block');

$this->setTitle('Resources');
$this->setTab('resources');

// presently, the 3rd-panel blocks are being AJAX'd, so just blank their vars and render them as-is
// that way you know what's meant to be in the template, rather than just gues
$this->setVar(array(
    'FOLDER_MIME_IMG' => '',
    'FOLDER_NAME' => '',
    'FOLDER_TEMPLATE' => '',
    'FOLDER_TYPE' => '',
    'FOLDER_PERM_GROUP' => '',
    'FOLDER_PERM_ACCESS' => '',

    'FILE_MIME_IMG' => '',
    'FILE_NAME' => '',
    'FILE_DESCRIPTION' => '',
    'FILE_POSTED_BY' => '',
    'FILE_POSTED_DATE' => '',
    'FILE_MODIFIED_DATE' => '',

    'MULTIFILE_MIME_IMG' => '',
    'MULTIFILE_COUNT' => '',

));

$this->parse('folder_detail_block', 'folder_detail', false);
$this->parse('folder_detail_perms_block', 'folder_detail_perms', false);
$this->parse('file_detail_block', 'file_detail', false);
$this->parse('multi_file_detail_block', 'multi_file_detail', false);

$groupHandler = new Group;
$allowedFiles = new AllowedFile;

$categoryMapper = Folder\Mapper::getInstance();

if (is_numeric($this->arg[0])) {
    $_SESSION['resourcesID'] = $this->arg[0];
    SitePage::localRedir('/resources/');
} else {
    $openCategoryId = (int)$_SESSION['resourcesID'];
}

// check permissions on selected category
if ($groupHandler->getPermission($openCategoryId) < READ_ACCESS) {
    $openCategoryId = 0;
}

// Check if the category even exists
try {
    $openCategory = $categoryMapper->findById($openCategoryId);

    // log view
    $categoryMapper->logPageView($openCategory);
} catch (Schoolbox\Mapper\Exception\Deleted $e) {
    // Deleted: load the parent
    $openCategory = $e->getDeletedObject()->getParent();
}
catch (Schoolbox\Mapper\Exception $e) {
    $openCategory = $categoryMapper->createModel();
    $openCategory->setId(0);
}

// Build a tree up to the last resource we looked at
$treeBuilder = new Folder\TreeBuilder(
    Folder\Mapper::getInstance(),
    TaskMapper::getInstance(),
    UserAccessMapper::getInstance(),
    RoleAccessMapper::getInstance(),
    $_SESSION['user']
);

// output folders, column 1 - now done via jsTree and TREEDATA
$this->setVar(
    array(
        'TREEDATA'      => json_encode($treeBuilder->buildPartialTree($openCategory->getId())),
        'OPEN_CATEGORY' => intval($openCategory->getId()),
    )
);

/* Output a list of nodes (which are comprised of both files and links)
 * in the second column of Old Resource Browser
 *
 * Root folder is zero and no links should be in there
 * Root folder is the default location to open upon first visit
 * and when a folder doesn't exist anymore
 */
$contents = new \Schoolbox\Resource\Browser\Tree\Contents($openCategory);

/* phplib
 */
if ($contents->getAllNodes()->count()) {
    foreach ($contents->getAllNodes() as $currentNode) {
        $output = array(
            'FILE_ID' => $currentNode->getNodeId(),
            'TYPE' => $currentNode->getType(),
            'FILE_NAME' => $currentNode->getName(),
            'FILE_SIZE' => $currentNode->getSize(),
            'CATEGORY_ID' => intval($currentNode->getCategory()->getId()),
            'CLASS' => $currentNode->getCss(),
            'FILE_LIST_TYPE' => $currentNode->getCss()
        );

        $this->setVar($output);
        $this->parse('file_list_block', 'file_list', true);
    }
} else {
    $this->setVar('file_list_block', '');
}

// output mime classes
$allowedList = $allowedFiles->getAll();
if (is_array($allowedList)) {
// append link icon to resutls
    $allowedList[]['iconFile'] = "link.png";
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
