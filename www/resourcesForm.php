<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
	
$page = new SitePage;
$groupHandler = new Group;
$categoryHandler = new Category;

if (is_numeric($_POST['folders'])) {

	$categoryList = $categoryHandler->getCategoryList($_POST['folders']);
	$output = array();
	if (is_array($categoryList)) {
		foreach ($categoryList as $category) {
			
			// get permissions
			if (!isset($permission[$category['id']]))
				$permission[$category['id']] = $groupHandler->getPermission($category['id']);

			if ($permission[$category['id']] >= READ_ACCESS) {
			
				//append code to name
				$name = scrubTEXT($category['name']);
				if($category['code'] != '' && $category['code'] != $category['name'])
					$name .= ' ('.scrubTEXT($category['code']).')';
			
				$children = (int)$categoryHandler->hasChildren($category['id']);
				$output[] = array('id'			=> $category['id'],
								  'name'		=> $name,
								  'parent'		=> $category['parent'],
								  'children'	=> $children,
								  'homepage'	=> $category['template'] > 0 ? 'true' : 'false',
								  'write'		=> $permission[$category['id']] == WRITE_ACCESS ? 'true' : 'false',
								  'full'		=> $permission[$category['id']] == FULL_ACCESS ? 'true' : 'false',
								  );
			}
		}
	}
	
	header("Content-type: application/json");
	print json_encode($output);
}
elseif (is_numeric($_POST['files'])) {

	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';
	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/link.php';
	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';

	$fileHandler = new File;
	$linkHandler = new Link;
	$allowedFiles = new AllowedFile;
	
	// save current view
	$_SESSION['resourcesID'] = $_POST['files'];
	
	// log view
	$categoryHandler->logPageView($_SESSION['resourcesID']);
	
	$fileList = $fileHandler->mergeLinks( $fileHandler->getAllInCategory($_POST['files']), $linkHandler->getAllInCategory($_POST['files']));
	$permission = $groupHandler->getPermission($_POST['files']);
	$output = array();
	if (is_array($fileList) && $permission >= READ_ACCESS) {
		foreach ($fileList as $file) {
			
			if (empty($file['modified']))
				$file['modified'] = $file['submitted'];
			
			$output[] = array('id'			=> $file['id'],
							  'name'		=> scrubText($file['description']),
							  'category'	=> $file['category'],
							  'modified'	=> getRelativeTime($file['modified'], 0, ''),
							//'size'		=> $file['file_size'] > 0 ? bytesToString($file['file_size']) : "--",
							  'owner'		=> formatName($file),
							  'icon'		=> !empty($file['iconFile']) ? $allowedFiles->getClass($file["iconFile"]) : "textfile",
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