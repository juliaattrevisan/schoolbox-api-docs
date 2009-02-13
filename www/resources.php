<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/link.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';

$this->setFile(TEMPLATE_content, 'resources.html');
$this->setBlock(TEMPLATE_content, 'category_list', 'category_list_block');
$this->setBlock(TEMPLATE_content, 'file_list', 'file_list_block');
$this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');

$this->setJavascript('/javascript/jquery.js');
$this->setJavascript('/javascript/jquery.ui.js');
$this->setJavascript('/javascript/jquery.contextmenu.js');
$this->setJavascript('/javascript/jquery.slimbox.js');
$this->setJavascript('/javascript/jquery.resources.js');

$this->setCss('/css/jquery.contextmenu.css');
$this->setCss('/css/jquery.slimbox.css');
$this->setCss('/css/jquery.resources.css');



$this->setTitle('Resources');
$this->setTab('resources');

$categoryHandler = new Category;
$fileHandler = new File;
$linkHandler = new Link;	
$groupHandler = new Group;
$allowedFiles = new AllowedFile;

$openCategory = (int)$this->arg[0];

// get parentIDs from openCategory to 0
$parentList[] = $parentID = $openCategory;
while ($parentID > 0)
	$parentList[] = $parentID = $categoryHandler->getParent($parentID);


// output folders, column 1
$categoryList = $categoryHandler->getCategoryNode($openCategory);
if (is_array($categoryList)) {
	
	foreach ($categoryList as $category) {
		$category['children'] = $categoryHandler->hasChildren($category['id']);
		
		$class = array();
		
		// set parent category
		$class[] = "child-of-node-{$category['parent']}";
		
		// has children
		$category['children'] > 0 ? $class[] = "parent" : null;
		
		// is currently expanded
		in_array($category['id'], $parentList) ? $class[] = "expanded populated" : null;
		
		// has files showing
		$category["id"] == $openCategory ? $class[] = "opened selected" : null;
		
		// has homepage
		$category['template'] > 0 ? $class[] = "homepage" : null;
		
		// write access
		$groupHandler->getPermission($category['id']) >= WRITE_ACCESS ? $class[] = "write" : null;
		
		$output = array('CATEGORY_ID'		=> $category['id'],
						'CATEGORY_NAME'		=> scrubText($category['name']),
						'CLASS'				=> implode(" ", $class),
						);	
		$this->setVar($output);
		$this->parse('category_list_block', 'category_list', true);
	}
}

// output files, column 2
$fileList = $fileHandler->mergeLinks( $fileHandler->getAllInCategory($openCategory), $linkHandler->getAllInCategory($openCategory));
$permission = $groupHandler->getPermission($openCategory);

if (is_array($fileList)) {
	
	foreach ($fileList as $file) {
		
		$class = array();
		
		// is link
		$file['url'] ? $class[] = "link" : null;
		
		// write access
		$permission >= WRITE_ACCESS ? $class[] = "write" : null;

		// is hidden
		$file['hidden'] > 0 ? $class[] = "hidden" : null;
		
		// icon
		$class[] = !empty($file['iconFile']) ? substr($file["iconFile"], 0, strpos($file["iconFile"], ".")) : "textfile";
	
		$output = array('FILE_ID' 	 	=> $file['id'],
						'FILE_NAME' 	=> scrubText($file['description']),
						'FILE_MODIFIED'	=> outputDate(DATE_short_format, $file['submitted']),
						'FILE_SIZE'		=> $file['file_size'] > 0 ? bytesToString($file['file_size']) : "--",
						'CATEGORY_ID'	=> $file['category'],
						'CLASS'			=> implode(" ", $class),
						);
						
		$this->setVar($output);
		$this->parse('file_list_block', 'file_list', true);

	}
} else
	$this->setVar('file_list_block', '');	

// output mime classes
$allowedList = $allowedFiles->getAll();
if (is_array($allowedList)) {
	
	// append link icon to resutls
	$allowedList[]['iconFile'] = "http-url.png";
	$last = '';
	foreach ($allowedList as $allowed) {
		if ($last != $allowed["iconFile"]) {
			$output = array("NAME"	=> substr($allowed["iconFile"], 0, strpos($allowed["iconFile"], ".")),
							"ICON"	=> $allowed["iconFile"],
							);
			
			$this->setVar($output);
			$this->parse("allowed_files_block", "allowed_files", true);
			
			$last = $allowed["iconFile"];
		}
	}
} else
	$this->setVar('allowed_files_block', '');

?>