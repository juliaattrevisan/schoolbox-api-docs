<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';

$page = new SitePage;

if (is_numeric($_POST['folders'])) {

	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-folder.php';
	$folderHandler = new MyFolder;

	$folderList = $folderHandler->getFolderList($_POST['folders']);
	$output = array();
	if (is_array($folderList)) {
		foreach ($folderList as $folder) {
			$children = (int)$folderHandler->hasChildren($folder['id']);
			$output[] = array('id'			=> $folder['id'],
							  'name'		=> scrubText($folder['name']),
							  'parent'		=> $folder['parent'],
							  'children'	=> $children,
							  );
		}
	}
	header("Content-type: application/json");
	print json_encode($output);
}
elseif (is_numeric($_POST['files'])) {

	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-file.php';
	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';
	
	$fileHandler = new MyFile;
	$allowedFiles = new AllowedFile;
	
	// save current view
	$_SESSION['portfolioID'] = $_POST['files'];
	
	$fileList = $fileHandler->getAllInFolder($_POST['files']);
	
	$output = array();
	if (is_array($fileList)) {
		foreach ($fileList as $file) {
			$output[] = array('id'			=> $file['id'],
							  'name'		=> scrubText($file['description']),
							  'category'	=> $file['category'],
							  'modified'	=> outputDate(DATE_short_format, $file['submitted']),
							  'size'		=> $file['file_size'] > 0 ? bytesToString($file['file_size']) : "--",
							  'icon'		=> !empty($file['iconFile']) ? $allowedFiles->getClass($file["iconFile"]) : "textfile",
							  );
		}
	}
	
	header("Content-type: application/json");
	print json_encode($output);
} 
elseif (is_numeric($_POST['saveID'])) {
	// save current view
	$_SESSION['portfolioID'] = $_POST['saveID'];
}


?>