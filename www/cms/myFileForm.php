<?php

session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio.php';

if (Cfg::get('webdav_enable')) {
	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/portfolioWebDAV.php';
	$fileHandler = PortfolioWebDAV::factory();
} else {
	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-file.php';
	$fileHandler = new MyFile;
}


$page = new SitePage;



if (isset($_POST['description']) || isset($_FILES['file']['tmp_name'])) {

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
	} else {
		$allowedFileHandler = new AllowedFile;
		$allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));
		
		$fileHandler->create($file, $_FILES['file']);
	}
	
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

/* Publish this file to the file owner's ePortfolio
 */
else if (is_numeric($_GET['publish'])) {
   if (!$file = $fileHandler->getByID($_GET['publish'])) {
       error(2);
   }

   $eportfolioHandler = new ePortfolio($file['owner']);

   if (!$eportfolioHandler->canEdit($file['owner'])) {
       error(109);
   }

   $file['published'] = 'NOW()';

   $fileHandler->update($file);

   localRedir('/cms/portfolio/' . $_POST['parent']);
}

/* Un-publish this file from the file owner's ePortfolio
 */
else if (is_numeric($_GET['unpublish'])) {
    if (!$file = $fileHandler->getByID($_GET['unpublish'])) {
        error(2);
    }

    $eportfolioHandler = new ePortfolio($file['owner']);

    if (!$eportfolioHandler->canEdit($file['owner'])) {
        error(109);
    }

    $file['published'] = 'NULL';

    $fileHandler->update($file);

    $page->backToTheFuture(1);
}
?>