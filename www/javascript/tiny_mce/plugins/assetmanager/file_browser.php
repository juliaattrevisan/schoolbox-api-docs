<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';

$page = new SitePage();
$page->template->set_file(TEMPLATE_body, dirname(__FILE__) . '/file_browser.html');
$page->setBlock(TEMPLATE_body, 'folder_list', 'folder_list_block');
$page->setBlock(TEMPLATE_body, 'file', 'file_block');
$page->setBlock('file', 'thumbnail_image', 'thumbnail_image_block');
$page->setBlock('file', 'thumbnail_flash', 'thumbnail_flash_block');

if (!is_numeric($_GET['categoryID'])) {
	die('Invalid category id');
}

$groupHandler = new Group();
$categoryHandler = new Category();
$fileHandler = new File();

$category = $categoryHandler->getByID($_GET['categoryID']);

$page->setVar(array(
	'FILE_CATEGORY_ID' => $_GET['categoryID'],
	'REQUEST_URI' => $_SERVER['REQUEST_URI'],
));

/* Output folder tree
 * Preselect path to the current category node
 */
$tree = new Tree_Category_Filebrowser($page);
$tree->setSelectedItem($_GET['categoryID']);
$tree->grow();
$tree->render();

/* Dump out a list of node id's of parents
 */
$page->setVar(array(
	'PARENTLIST' => $tree->getJsAncestorNodesList()
));

/* output files
 */
if (in_array($_GET['filter'], array('image', 'video'))) {
	$filter = array(
		'filter' => $_GET['filter']
	);

	$page->setVar(array(
		'FILTER' => $_GET['filter']
	));
}
else {
	$filter = null;

	$page->setVar(array(
		'FILTER' => ''
	));
}

$fileList = $fileHandler->getAllInCategory($_GET['categoryID'], $filter);

if (is_array($fileList)) {

    // add homepage "file"
    if ($category['template'] > 0) {
        $homepage = array(
			'id' => $category['id'],
			'description' => 'Homepage',
			'size' => 1,
			'iconFile' => 'vcard_fiquet.png',
		);

		array_unshift($fileList, $homepage);
    }

	foreach ($fileList as $file) {
		$page->setVar(array (
			'FILE_ID' => $file['id'],
			'DESCRIPTION' => $file['description'],
			'TITLE_SHORT' => TextFormatter::cutString($file['description'], 10),
			'SIZE' => round($file['file_size'] / 1024),
			'FILE_ICON' => $file['iconFile'],
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

