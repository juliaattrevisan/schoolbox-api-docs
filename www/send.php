<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//this script opens files for download and send them to the user with correct filename
//future enhancements could include storing the content type to help browser file recognition
//rather than just octet stream

//includes
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
	
if(is_numeric($_GET['id']))
{
	$page = new SitePage;
	$groupHandler = new Group;
	
	
	if(isset($_GET['myFile'])) {
		
		if (WEBDAV_on) {
			require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/portfolioWebDAV.php';
			$fileHandler = PortfolioWebDAV::factory();
			$filename = $fileHandler->getFile($_GET['id']);
			$file['realname'] = substr($_SESSION['WebDAVIDCache'][$_GET['id']], strrpos($_SESSION['WebDAVIDCache'][$_GET['id']], '/') + 1);
		} else {
			require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-file.php';
			
			$myFileHandler = new MyFile;
			$file = $myFileHandler->getByID($_GET['id']);
			if(!is_array($file))
				error(41);
				
			if($file['owner'] != $_SESSION['id'])
				error(5);
			
			$filename = MYFILE_directory ."/". $file['id'] . ".dat";
		}
		
		
	}
	else {
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';
		
		$fileHandler = new File;
		$file = $fileHandler->getByID($_GET['id']);
		if(!is_array($file))
			error(41);
			
		if($groupHandler->getPermission($file['category']) < READ_ACCESS) 
			error(5);
		
		$filename = FILE_directory ."/". $_GET['id'] . ".dat";
	
	}
	
	
	if($_GET['save'])
		$attach = "attachment;";
	else
		$attach = "";
	
	$filesize = @filesize($filename);
	if($filesize == FALSE) //if we cannot find the file (something is wrong dont link to it)
		error(41);

	//send headers
	if($file['mime_type'])		
		header ("Content-Type: {$file['mime_type']}"); 
	else
		header ("Content-Type: application/octet-stream"); 

	header ("Pragma: ");
	header ("Cache-control: ");			
	header ("Content-Disposition: $attach filename=\"".$file['realname']."\""); 
	header ("Content-Length: $filesize");	
	readfile($filename);
}
else
	error(2);

?>
