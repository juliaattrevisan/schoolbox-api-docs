<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Assessment\Course\TaskMapper;

$this->setFile(TEMPLATE_content, 'resources.html');
$this->setBlock(TEMPLATE_content, 'file_list', 'file_list_block');
$this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');

// 3rd-panel blocks
$this->setBlock(TEMPLATE_content, 'folder_detail', 'folder_detail_block');
$this->setBlock('folder_detail', 'folder_detail_perms', 'folder_detail_perms_block');
$this->setBlock(TEMPLATE_content, 'file_detail', 'file_detail_block');
$this->setBlock(TEMPLATE_content, 'multi_file_detail', 'multi_file_detail_block');

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


$this->setTitle('Resources');
$this->setTab('resources');

$categoryHandler = new Category;
$fileHandler = new File;
$linkHandler = new Link;
$groupHandler = new Group;
$allowedFiles = new AllowedFile;


if (is_numeric($this->arg[0])) {
	$_SESSION['resourcesID'] = $this->arg[0];
	SitePage::localRedir('/resources/');
}
else
{
	$openCategory = (int)$_SESSION['resourcesID'];
}

// check permissions on selected category
if ($groupHandler->getPermission($openCategory) < READ_ACCESS) {
	$openCategory = 0;
}

// Check if the category even exists
$categoryMapper = CategoryMapper::getInstance();
try {
    $categoryMapper->findById($openCategory);
} catch (Schoolbox\Mapper\Exception\Deleted $e) {
    // Deleted: load the parent
    $openCategory = $e->getDeletedObject()->getParent()->getId();
} catch (Schoolbox\Mapper\Exception $e) {
    $openCategory = 0;
}

// log view
if ($openCategory > 0) {
	$categoryHandler->logPageView($openCategory);
}

// Build a tree up to the last resource we looked at
$treeBuilder = new Schoolbox\Resource\CategoryTreeBuilder(
    $categoryMapper,
	TaskMapper::getInstance(),
	$groupHandler
);

// output folders, column 1 - now done via jsTree and TREEDATA
$this->setVar(
    array(
        'TREEDATA'      => json_encode($treeBuilder->buildPartialTree($openCategory)),
        'OPEN_CATEGORY' => intval($openCategory),
    )
);

// output files, column 2
$allLinkNodes = new \Schoolbox\Collection();

/* Display links in the opened folder
 * Root folder is zero and no links should be in there
 */
if ($openCategory) {
    try {
        $categoryMapper = new \Schoolbox\Resource\CategoryMapper();
        $category = $categoryMapper->findById($openCategory);

        $linkMapper = new \Schoolbox\Resource\LinkMapper();
        $allLinkNodes = $linkMapper->findAllInCategory($category, array());
    }
    catch (\Exception $e) {
        error(13);
    }
}

$allFileNodes = new \Schoolbox\Collection($fileHandler->getAllInCategory($openCategory));

/* Pseudo-composite
 */
$allNodes = new \Schoolbox\Collection();
$allNodes->merge($allFileNodes);
$allNodes->merge($allLinkNodes);

/* TODO: Sort by Description
 */

$permission = $groupHandler->getPermission($openCategory);

if ($allNodes->count()) {
	$userHandler = new User();

	foreach ($allNodes as $currentNode) {

        /* phpliberalism on Link nodes
         */
        if ($currentNode instanceof \Schoolbox\Resource\Link) {
            $class = array('link');

            $output = array(
                'FILE_ID' => $currentNode->getId(),
                'TYPE' => 'link',
                'FILE_NAME' => Scrub::text($currentNode->getDescription()),
                'FILE_SIZE' => "--",
                'CATEGORY_ID' => $category->getId(),
                'CLASS' => implode(" ", $class),
                'FILE_LIST_TYPE' => 'link'
            );

            $this->setVar($output);
            $this->parse('file_list_block', 'file_list', true);
        }
        else {

            // if the file is associated with due work, it shouldn't be visible here
            if($currentNode['type'] || $currentNode['typeName']) {
                continue;
            }

            if (empty($currentNode['modified'])) {
                $currentNode['modified'] = $currentNode['submitted'];
            }

            $class = array();

            // write access
            $permission >= WRITE_ACCESS ? $class[] = "write" : null;

            // is hidden
            $currentNode['hidden'] > 0 ? $class[] = "hidden" : null;

            // icon
            $class[] = !empty($currentNode['iconFile']) ? $allowedFiles->getClass($currentNode["iconFile"]) : "binary";

            $fileModifiedTime = new Alaress_Date_Relative($currentNode['modified']);

            /* Detect which icon to display
             * $allowedFiles->getClass() returns empty string for iconFile 'html_alt1'
             */
            if (!empty($currentNode['iconFile'])) {
                $fileListType = $allowedFiles->getClass($currentNode["iconFile"]);
            }
            else {
                $fileListType = "textfile";
            }

            $output = array(
                'FILE_ID' => $currentNode['id'],
                'TYPE' => 'file',
                'FILE_NAME' => Scrub::text($currentNode['description']),
                'FILE_SIZE' => $currentNode['file_size'] > 0 ? Alaress_Byte_Format::bytesToString($currentNode['file_size']) : "--",
                'CATEGORY_ID' => $currentNode['category'],
                'CLASS' => implode(" ", $class),
                'FILE_LIST_TYPE' => $fileListType
            );

            $this->setVar($output);
            $this->parse('file_list_block', 'file_list', true);
        }
	}
}
else
{
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
}
else
{
	$this->setVar('allowed_files_block', '');
}

