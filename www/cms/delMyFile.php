<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//this script allows users to delete resources from the system
require_once("../include/include.php");
require_once("../include/session.php");

if(isset($_GET['delid'])  && $_GET['delid'])
{
	include("myFile.php");

	$fileDB = new myFile;
	$fileInfo = $fileDB->getFile($_GET['delid']);
	
	if(!empty($myFile['owner']) && $myFile['owner'] != $_SESSION['id']){
		error(48);
	}

	
	//delete file, security checks done within function
	$fileDB->deleteFile($_GET['delid']);	

	localRedir("/cms/browseMyFiles.php?catid=".$fileInfo['parent']);
	exit;
}
else
{
	error(2);
}
