<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-file.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$page = new SitePage;
$fileHandler = new File;
$groupHandler = new Group;

if($_POST['description'] && is_numeric($_POST['category'])) {

	if ($groupHandler->getPermission($_POST['category']) < WRITE_ACCESS)
		error(6);

	$file = array(	'description'	=> $_POST['description'],
					'comment'		=> $_POST['comment'],
					'hidden'		=> $_POST['hidden'],
				);

	if(is_numeric($_POST['fileID'])) {
		
		/* Modify existing file */
		
		/* Double check permission on file */
		$currentfile = $fileHandler->getById($_POST['fileID']);
		if ($groupHandler->getPermission($currentfile['cid'] < WRITE_ACCESS)
			error(6);
		
		$file['id'] = $_POST['fileID'];
		$fileHandler->update($file);
		
		$page->backToTheFuture(2);
	}
	else {
		/* Add file */
		$file['category'] = $_POST['category'];
		$file['owner'] = $_SESSION['id'];
		$allowedFileHandler = new AllowedFile;
		
		if($_FILES['file']['name'])	{
			/* Submitted file with form, run security checks */	
			$allowedFileHandler->fileSecurityCheck($_FILES['file']);
			
			/* Submit to multiple locations */
			if($_POST['catCount']) {
				for($x=0; $_POST['catCount'] > $x; $x++) {	
					if($_POST['mulc'][$x]) {
						$file['category'] = $_POST['mulc'][$x];
						$fileID = $fileHandler->create($file, $_FILES['file']);
					}
				}
			}
			else {	/* Basic file submit */
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
			$fileHandler->create($file, $myFile);
		
		}
		else {
			$fileHandler->create($file);
		}
		
		/* Redirect to submission page */
		if($_POST['submission'])
			localRedir("/submission/createSubmission.php?category=".$_POST['category']."&fileid=".$fileID);
		else
			$page->backToTheFuture(2);
	}
}
else if($_GET['delete']) {

	if($file = $fileHandler->getByID($_GET['delete']))
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
