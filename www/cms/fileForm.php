<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-file.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$page = new SitePage;
$fileHandler = new File;
$groupHandler = new Group;

if(is_string($_POST['description']) && (is_numeric($_POST['category']) || is_array($_POST['category']) ) ) {

	$file = array(	'description'	=> $_POST['description'],
					'comment'		=> $_POST['comment'],
					'hidden'		=> $_POST['hidden'],
				);

	if(is_numeric($_POST['fileID'])) {
		
		/* Modify existing file */
		
		/* Double check permission on file */
		$currentfile = $fileHandler->getById($_POST['fileID']);
		if ($groupHandler->getPermission($currentfile['cid']) < WRITE_ACCESS)
			error(6);
		
		$file['id'] = $_POST['fileID'];
		$fileHandler->update($file);
		
		$page->backToTheFuture(2);
	}
	else {
		/* Add file */
		$file['owner'] = $_SESSION['id'];
		$allowedFileHandler = new AllowedFile;
		
		if($_FILES['file']['name'])	{
			/* Submitted file with form, run security checks */	
			$allowedFileHandler->fileSecurityCheck($_FILES['file']);
			
			/* Add to multiple locations */
			if(is_array($_POST['category']) ){
				foreach($_POST['category'] as $cat ){
					
					if ($groupHandler->getPermission($cat) < WRITE_ACCESS)
						error(6);
					
					$file['category'] = $cat;
					$fileHandler->create($file, $_FILES['file']);
				}
			}
			else { 
				if ($groupHandler->getPermission($_POST['category']) < WRITE_ACCESS)
						error(6);
			
				$file['category'] = $_POST['category'];	
				$fileID = $fileHandler->create($file, $_FILES['file']);
			}
				
		}
		else if($_POST['myfile']) {
			$myFileHandler = new MyFile;
			$privFile = $myFileHandler->getByID($_POST['myfile']);
		
			$myFile = array('name' 		=> $privFile['realname'],
							'type' 		=> $privFile['mime_type'],
							'tmp_name'	=> MYFILE_directory . '/' . $privFile['id'] . '.dat',
							);
			/* Make sure the mime is ok for public */
			$allowedFileHandler->checkMime($myFile);
			
			if ($groupHandler->getPermission($_POST['category']) < WRITE_ACCESS)
				error(6);
			
			$file['category'] = $_POST['category'];	
			$fileHandler->create($file, $myFile);
		
		}
		
		/* Redirect to submission page */
		if($_POST['submission'])
			localRedir("/submission/createSubmission.php?category=".$_POST['category']."&fileid=".$fileID);
		else
			$page->backToTheFuture(2);
	}
}
else if(is_array($_POST['description']) && is_numeric($_POST['category'])) {
	
	/* Upload multiple files to single location */
	if ($groupHandler->getPermission($_POST['category']) < WRITE_ACCESS)
		error(6);

	for($x = 0; $_POST['description'][$x]; $x++) {
		$file = array(	'description' 	=> $_POST['description'][$x],
						'hidden'		=> $_POST['hidden'],
						'category'		=> $_POST['category'], 
						'owner' 		=> $_SESSION['id'],
					);
					
		//grab file out of silly array
		foreach($_FILES['file'] as $key => $value) {
			$fileData[$key] = $value[$x];
		}			
					
		$fileID = $fileHandler->create($file, $fileData);
	}
	
	$page->backToTheFuture(2);
	
}
else if(is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
	
	/* Move file from one category to another, after checking permission on both from and to categories */
	if(!$file = $fileHandler->getByID($_POST['move']))
		echo "Failed";

	if ($groupHandler->getPermission($file['cid']) < WRITE_ACCESS)
		echo "Insufficient permssions";

	if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS)
		echo "Insufficient permssions";
	
	$fileHandler->update(array('id'=>$_POST['move'], 'category'=>$_POST['categoryID']));

}
else if(is_numeric($_GET['delete'])) {
	
	/* Delete file from system after permission checks */
	
	if(!$file = $fileHandler->getByID($_GET['delete']))
		error(2);

	if ($groupHandler->getPermission($file['cid']) < WRITE_ACCESS)
		error(6);

	$fileHandler->delete($_GET['delete']);
	
	$page->backToTheFuture(1);
}
else {
	error(2);
}



?>
