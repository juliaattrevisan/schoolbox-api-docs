<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';

$page = new SitePage;
$page->template->set_file(TEMPLATE_body, dirname(__FILE__) . '/image_browser.html');
$page->setBlock(TEMPLATE_body, 'folder_list', 'folder_list_block');
$page->setBlock(TEMPLATE_body, 'file', 'file_block');

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
			$name = scrubTEXT($category['name']);
			if(!empty($category['code']) && $category['code'] != $category['name'])
				$name .= ' ('.scrubTEXT($category['code']).')';			
			
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
	


/* output files */
$fileList = $fileHandler->getAllInCategory($_GET['categoryID'], array('filter' => 'image'));

if (is_array($fileList)) {
	foreach ($fileList as $file) {
		
		$output = array ( 'FILE_ID' 	=> $file['id'],
						  'DESCRIPTION'	=> $file['description'],
						  'TITLE_SHORT' => cutString($file['description'], 10),
						  'SIZE'  		=> round($file['file_size']/1024),
						  );
		$page->setVar($output);
		$page->parse('file_block', 'file', true);
		
	}
} else
	$page->setVar('file_block', '');

$page->outputPage(true);

?>