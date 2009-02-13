<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/link.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
$page = new SitePage;
$categoryHandler = new Category;
$fileHandler = new File;
$linkHandler = new Link;
$groupHandler = new Group;

if (is_numeric($_GET['folders'])) {
	$categoryList = $categoryHandler->getCategoryList($_GET['folders']);
	$output = array();
	if (is_array($categoryList)) {
		foreach ($categoryList as $category) {
			$children = (int)$categoryHandler->hasChildren($category['id']);
			$output[] = array('id'			=> $category['id'],
							  'name'		=> scrubText($category['name']),
							  'parent'		=> $category['parent'],
							  'children'	=> $children,
							  );
		}
	}
	header("Content-type: application/json");
	print json_encode($output);
}
elseif (is_numeric($_GET['files'])) {
	$fileList = $fileHandler->mergeLinks( $fileHandler->getAllInCategory($_GET['files']), $linkHandler->getAllInCategory($_GET['files']));
	$permission = $groupHandler->getPermission($_GET['files']);
	$output = array();
	if (is_array($fileList)) {
		foreach ($fileList as $file) {
			$output[] = array('id'			=> $file['id'],
							  'name'		=> scrubText($file['description']),
							  'category'	=> $file['category'],
							  'modified'	=> outputDate(DATE_short_format, $file['submitted']),
							  'size'		=> $file['file_size'] > 0 ? bytesToString($file['file_size']) : "--",
							  'icon'		=> !empty($file['iconFile']) ? substr($file["iconFile"], 0, strpos($file["iconFile"], ".")) : "textfile",
							  'link'		=> isset($file['url']) ? 'true' : 'false',
							  'write'		=> $permission >= WRITE_ACCESS ? 'true' : 'false',
							  );
		}
	}
	
	header("Content-type: application/json");
	print json_encode($output);
}


?>