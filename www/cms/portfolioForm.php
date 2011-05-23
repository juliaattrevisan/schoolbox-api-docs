<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';

$page = new SitePage;

if (is_numeric($_POST['relationID'])) {

	if (Cfg::get('webdav_enable')) {
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/portfolioWebDAV.php';
		$folderHandler = PortfolioWebDAV::factory();
	} else {
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-folder.php';
		$folderHandler = new MyFolder;
	}
	
	$folderList = $folderHandler->getFolderList($_POST['relationID']);
	$output = array();
	if (is_array($folderList)) {
		foreach ($folderList as $folder) {
			$children = (int)$folderHandler->hasChildren($folder['id']);
			$output[] = array('id'			=> $folder['id'],
							  'name'		=> scrubText($folder['name']),
							  'parent'		=> $folder['parent'],
							  'isParent'	=> $children ? 1 : 0,
							  );
		}
	}
	header("Content-type: application/json");
	print json_encode($output);
}
elseif (is_numeric($_POST['categoryID'])) {

	

	if (Cfg::get('webdav_enable')) {
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/portfolioWebDAV.php';
		$fileHandler = PortfolioWebDAV::factory();
	} else {
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-file.php';
		$fileHandler = new MyFile;
		$allowedFiles = new AllowedFile;
	}
	
	
	
	
	// save current view
	$_SESSION['portfolioID'] = $_POST['files'];
	
	$fileList = $fileHandler->getAllInFolder($_POST['categoryID']);
	
	$output = array();
	if (is_array($fileList)) {
		foreach ($fileList as $file) {
			$output[] = array('id'			=> $file['id'],
							  'name'		=> scrubText($file['description']),
							  'category'	=> $file['category'],
							  'modified'	=> outputDate(Cfg::get('date_short'), $file['submitted']),
							  'size'		=> $file['file_size'] > 0 ? bytesToString($file['file_size']) : "--",
							  'icon'		=> !empty($file['iconFile']) && Cfg::get('webdav_enable') ? $allowedFiles->getClass($file["iconFile"]) : "textfile",
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