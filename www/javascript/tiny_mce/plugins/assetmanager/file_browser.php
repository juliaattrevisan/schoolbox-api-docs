<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

$page = new SitePage;
$page->template->set_file(TEMPLATE_body, dirname(__FILE__) . '/file_browser.html');
$page->setBlock(TEMPLATE_body, 'folder_list', 'folder_list_block');
$page->setBlock(TEMPLATE_body, 'file', 'file_block');
$page->setBlock('file', 'thumbnail_image', 'thumbnail_image_block');
$page->setBlock('file', 'thumbnail_flash', 'thumbnail_flash_block');

if (!is_numeric($_GET['categoryID']))
	die('Invalid category id');

$groupHandler = new Group;
$categoryHandler = new Category;
$fileHandler = new File;

$output = array('FILE_CATEGORY_ID'	=> $_GET['categoryID'],
				'REQUEST_URI'		=> $_SERVER['REQUEST_URI'],
				);
$page->setVar($output);

/* get a list of open ids */
$parentList[] = $parentID = $_GET['categoryID'];
$i = 0;
while ($parentID > 0) {
	if ($i++ > 100) break;
	$parentList[] = $parentID = (int)$categoryHandler->getParent($parentID);
}

/* output folders */
$categoryList = $categoryHandler->getCategoryNode($_GET['categoryID']);

$page->setVar('folder_list_block', '');
if (is_array($categoryList)) {

    foreach ($categoryList as $category) {

		$permission[$category['id']] = $groupHandler->getPermission($category['id']);
		if ($permission[$category['id']] >= READ_ACCESS) {

			$class = array();

			$class[] = 'child-of-node-' . $category['parent'];

			/* check upload permissions */
			if ($permission[$category['id']] == WRITE_ACCESS)
				$class[] = 'write';

			/* add plus sign to parent nodes */
			if ($category['isParent'] == '1')
				$class[] = in_array($category['id'], $parentList) ? 'expanded loaded' : 'collapsed';

			/* append code to name */
			$name = Scrub::text($category['name']);
			if(!empty($category['code']) && $category['code'] != $category['name'])
				$name .= ' ('.Scrub::text($category['code']).')';

			/* select default dir */
			if ($category['id'] == $_GET['categoryID'])
				$class[] = 'selected';

			$output = array('CATEGORY_ID'	=> $category['id'],
							'TITLE'			=> $name,
							'CLASS'			=> implode(" ", $class),
							);
			$page->setVar($output);
			$page->parse('folder_list_block', 'folder_list', true);

		}
	}
}



$category = $categoryHandler->getByID($_GET['categoryID']);

/* output files */
if (in_array($_GET['filter'], array('image', 'video'))) {
    $filter = array('filter' => $_GET['filter']);
    $output = array('FILTER'    => $_GET['filter']);
} else {
    $filter = null;
    $output = array('FILTER'    => '');
}

$fileList = $fileHandler->getAllInCategory($_GET['categoryID'], $filter);


$page->setVar($output);

if (is_array($fileList)) {

    // add homepage "file"
    if ($category['template'] > 0 && !is_array($filter)) {
        $homepage = array('id'          => $category['id'],
                          'description' => 'Homepage',
                          'size'        => 1,
                          'iconFile'    => 'vcard_fiquet.png',
                          );

        array_unshift($fileList, $homepage);

    }

	foreach ($fileList as $file) {

		$output = array ( 'FILE_ID' 	=> $file['id'],
						  'DESCRIPTION'	=> $file['description'],
						  'TITLE_SHORT' => TextFormatter::cutString($file['description'], 10),
						  'SIZE'  		=> round($file['file_size']/1024),
						  'FILE_ICON'	=> $file['iconFile'],
						  );
		$page->setVar($output);

		$page->setVar('thumbnail_image_block', '');
		$page->setVar('thumbnail_flash_block', '');
		if ($filter['filter'] == 'image')
			$page->parse('thumbnail_image_block', 'thumbnail_image');
		else
			$page->parse('thumbnail_flash_block', 'thumbnail_flash');

		$page->parse('file_block', 'file', true);

	}
} else
	$page->setVar('file_block', '');

$page->outputPage(true);

?>