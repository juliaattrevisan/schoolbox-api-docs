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

if (is_numeric($_POST['folders'])) {
	$categoryList = $categoryHandler->getCategoryList($_POST['folders']);
	$output = array();
	if (is_array($categoryList)) {
		foreach ($categoryList as $category) {
			$permission = $groupHandler->getPermission($category['id']);
			$children = (int)$categoryHandler->hasChildren($category['id']);
			$output[] = array('id'			=> $category['id'],
							  'name'		=> scrubText($category['name']),
							  'parent'		=> $category['parent'],
							  'children'	=> $children,
							  'write'		=> $permission == WRITE_ACCESS ? 'true' : 'false',
							  'full'		=> $permission == FULL_ACCESS ? 'true' : 'false',
							  );
		}
	}
	
	header("Content-type: application/json");
	print json_encode($output);
}
elseif (is_numeric($_POST['files'])) {
	
	// save current view
	$_SESSION['resourcesID'] = $_POST['files'];
	
	$fileList = $fileHandler->mergeLinks( $fileHandler->getAllInCategory($_POST['files']), $linkHandler->getAllInCategory($_POST['files']));
	$permission = $groupHandler->getPermission($_POST['files']);
	$output = array();
	if (is_array($fileList)) {
		foreach ($fileList as $file) {
			$output[] = array('id'			=> $file['id'],
							  'name'		=> scrubText($file['description']),
							  'category'	=> $file['category'],
							  'modified'	=> outputDate(DATE_short_format, $file['modified']),
							//'size'		=> $file['file_size'] > 0 ? bytesToString($file['file_size']) : "--",
							  'owner'		=> formatName($file),
							  'icon'		=> !empty($file['iconFile']) ? substr($file["iconFile"], 0, strpos($file["iconFile"], ".")) : "textfile",
							  'link'		=> isset($file['url']) ? 'true' : 'false',
							  'hidden'		=> $file['hidden'] ? 'true' : 'false',
							  'write'		=> $permission == WRITE_ACCESS ? 'true' : 'false',
							  'full'		=> $permission == FULL_ACCESS ? 'true' : 'false',
							  );
		}
	}
	
	header("Content-type: application/json");
	print json_encode($output);
} 
elseif (is_numeric($_POST['saveID'])) {
	// save current view
	$_SESSION['resourcesID'] = $_POST['saveID'];
}


?>