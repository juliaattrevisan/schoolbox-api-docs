<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$page = new SitePage();
$page->template->set_file(TEMPLATE_body, dirname(__FILE__) . '/eportfolio_file_browser.html');
$page->setBlock(TEMPLATE_body, 'folder_list', 'folder_list_block');
$page->setBlock(TEMPLATE_body, 'file', 'file_block');
$page->setBlock('file', 'thumbnail_image', 'thumbnail_image_block');
$page->setBlock('file', 'thumbnail_flash', 'thumbnail_flash_block');

if (!is_numeric($_GET['userID'])) {
	die('Invalid user id');
}

$page->setVar(array(
    'FILE_USER_ID' => $_GET['userID'],
    'REQUEST_URI' => $_SERVER['REQUEST_URI'],
));

$folderHandler = new MyFolder($_GET['userID']);

$allFolders = $folderHandler->getFolderTree();

if (is_array($allFolders) && count($allFolders)) {
	$previousParent = -1;

	foreach($allFolders as $currentFolder) {
		$class = array();
		$class[] = 'child-of-node-' . $currentFolder['parent'];

		$output = array(
			'CATEGORY_ID' => $currentFolder['id'],
			'TITLE' => $currentFolder['name'],
			'CLASS' => implode(' ', $class),
		);

		$page->setVar($output);
		$page->parse('folder_list_block', 'folder_list', true);
	}
}
else {
	$page->setVar('folder_list_block', '');
}

/* Output files
 */
if (in_array($_GET['filter'], array('image', 'video'))) {
    $filter = array(
        'filter' => $_GET['filter']
    );

    $output = array(
        'FILTER' => $_GET['filter']
    );
}
else {
    $filter = null;
    $output = array(
        'FILTER' => ''
    );
}

$page->setVar($output);

/* Process files
 */
$fileHandler = new MyFile();

/* Note that categoryID is synonymous with the parent id
 */
$allFiles = $fileHandler->getAllByFields(array(
	'owner' => $_GET['userID'],
	'parent' => $_GET['categoryID'],
));

if (is_array($allFiles)) {
	foreach ($allFiles as $currentFile) {
        $page->setVar(array (
            'FILE_ID' => $currentFile['id'],
            'DESCRIPTION' => $currentFile['description'],
            'TITLE_SHORT' => TextFormatter::cutString($currentFile['description'], 10),
            'SIZE' => round($currentFile['file_size'] / 1024),
            'FILE_ICON' => $currentFile['iconFile'],
        ));

		$page->setVar('thumbnail_image_block', '');
		$page->setVar('thumbnail_flash_block', '');

		if ($filter == 'image') {
            $page->parse('thumbnail_image_block', 'thumbnail_image');
        }
        else {
            $page->parse('thumbnail_flash_block', 'thumbnail_flash');
        }

        $page->parse('file_block', 'file', true);
	}
}
else {
	$page->setVar('file_block', '');
}

$page->outputPage(true);

