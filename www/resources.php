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
	$openCategoryId = (int)$_SESSION['resourcesID'];
}

// check permissions on selected category
if ($groupHandler->getPermission($openCategoryId) < READ_ACCESS) {
	$openCategoryId = 0;
}

// Check if the category even exists
$categoryMapper = CategoryMapper::getInstance();
try {
    $categoryMapper->findById($openCategoryId);
} catch (Schoolbox\Mapper\Exception\Deleted $e) {
    // Deleted: load the parent
    $openCategoryId = $e->getDeletedObject()->getParent()->getId();
} catch (Schoolbox\Mapper\Exception $e) {
    $openCategoryId = 0;
}

// log view
if ($openCategoryId > 0) {
	$categoryHandler->logPageView($openCategoryId);
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
        'TREEDATA'      => json_encode($treeBuilder->buildPartialTree($openCategoryId)),
        'OPEN_CATEGORY' => intval($openCategoryId),
    )
);




/* Permissions
 */
$permission = $groupHandler->getPermission($openCategoryId);

/* Output a list of nodes (which are comprised of both files and links)
 * in the second column of Old Resource Browser
 */
$allLinkNodes = new \Schoolbox\Collection();
$allFileNodes = new \Schoolbox\Collection();

/* Display links in the opened (selected) folder
 */

/* Root folder is zero and no links should be in there
 * Root folder is the default location to open upon first visit
 * and when a folder doesn't exist anymore
 */
$category = new \Schoolbox\Resource\Category();
$category->setId(0);

try {
    $categoryMapper = new \Schoolbox\Resource\CategoryMapper();
    $category = $categoryMapper->findById($openCategoryId);
}
catch (\Exception $e) {
}

/* Adapt and overcome
 */
$allNodes = new \Schoolbox\Collection();

/* Links
 */
$linkMapper = new \Schoolbox\Resource\LinkMapper();
$allLinkNodes = $linkMapper->findAllInCategory($category, array());

/* Process links
 */
foreach ($allLinkNodes as $currentLinkNode) {
    $node = new \Schoolbox\Resource\Browser\Tree\Node();
    $node->setNodeId($currentLinkNode->getId());
    $node->setType('link');
    $node->setIcon('link');
    $node->setName(Scrub::text($currentLinkNode->getDescription()));
    $node->setCategory($category);
    $node->setCss(implode(' ', array('link')));
    $node->setModifiedAt($currentLinkNode->getSubmitted());

    $allNodes->add($node);
}

/* Files
 */
$allFileNodes = new \Schoolbox\Collection($fileHandler->getAllInCategory($openCategoryId));

/* Process files
 */
foreach ($allFileNodes as $currentFileNode) {

    // if the file is associated with due work, it shouldn't be visible here
    if ($currentFileNode['type'] || $currentFileNode['typeName']) {
        continue;
    }

    /* css for the node
     */
    $class = array();

    // write access
    if ($permission >= WRITE_ACCESS) {
        $class[] = "write";
    }

    // is hidden
    if ($currentFileNode['hidden'] > 0) {
        $class[] = "hidden";
    }

    // icon
    $class[] = !empty($currentFileNode['iconFile']) ? $allowedFiles->getClass($currentFileNode["iconFile"]) : "binary";

    /* Detect which icon to display
     * $allowedFiles->getClass() returns empty string for iconFile 'html_alt1'
     */
    if (!empty($currentFileNode['iconFile'])) {
        $fileListType = $allowedFiles->getClass($currentFileNode["iconFile"]);
    }
    else {
        $fileListType = "textfile";
    }

    $node = new \Schoolbox\Resource\Browser\Tree\Node();
    $node->setNodeId($currentFileNode['id']);
    $node->setType('file');
    $node->setIcon($fileListType);
    $node->setName(Scrub::text($currentFileNode['description']));
    $node->setSize($currentFileNode['file_size'] > 0 ? Alaress_Byte_Format::bytesToString($currentFileNode['file_size']) : "--");
    $node->setCategory($category);
    $node->setCss(implode(' ' , $class));
    $node->setModifiedAt(new \Alaress_Date_Relative($currentFileNode['modified']));

    $allNodes->add($node);
}

/* phplib
 */
if ($allNodes->count()) {

    /* Sort by name, then process all
     */
    $allNodes->sortBy('name', 'asc');

	foreach ($allNodes as $currentNode) {
        $output = array(
            'FILE_ID' => $currentNode->getNodeId(),
            'TYPE' => $currentNode->getType(),
            'FILE_NAME' => $currentNode->getName(),
            'FILE_SIZE' => $currentNode->getSize(),
            'CATEGORY_ID' => $currentNode->getCategory()->getId(),
            'CLASS' => $currentNode->getCss(),
            'FILE_LIST_TYPE' => $currentNode->getIcon()
        );

        $this->setVar($output);
        $this->parse('file_list_block', 'file_list', true);
    }
}
else {
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

