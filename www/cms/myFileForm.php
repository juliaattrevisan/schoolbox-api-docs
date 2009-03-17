<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-file.php';

$page = new SitePage;
$fileHandler = new MyFile;

if (isset($_POST['description'])) {

	$file = array('parent'		=> $_POST['parent'],
				  'description'	=> $_POST['description'],
				  );
				  
	if (is_numeric($_POST['fileID'])) {
		// check user owns file
		$current = $fileHandler->getByID($_POST['fileID']);
		if ($current['owner'] != $_SESSION['id'])
			$page->error(47);
	
		$file['id'] = $_POST['fileID'];
		$fileHandler->update($file);
	} else
		$fileHandler->create($file, $_FILES['file']);
		
	localRedir('/cms/portfolio/' . $_POST['parent']);
}
elseif (is_numeric($_GET['delete'])) {

	if(!$file = $fileHandler->getByID($_GET['delete']))
		error(2);
		
	if ($file['owner'] != $_SESSION['id'])
		$page->error(48);
			
	$fileHandler->delete($_GET['delete']);
	
	localRedir('/cms/portfolio/' . $file['parent']);
		
}
elseif (is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
	/* Move file from one category to another, after checking permission on both from and to categories */
	if(!$file = $fileHandler->getByID($_POST['move']))
		echo "Failed";

	if ($file['owner'] != $_SESSION['id'])
		echo "Insufficient permssions";
	
	$fileHandler->update(array('id'=>$_POST['move'], 'parent'=>$_POST['categoryID']));
}

?>