<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//this script allows users to delete resources from the system
require_once("../include/include.php");
require_once("../include/session.php");

if(isset($_GET['fileid'])  && $_GET['fileid'])
{
	include("file.php");

	$fileDB = new file;
	$fileInfo = $fileDB->getFile($_GET['fileid']);
	
	//delete file, security checks done within function
	$fileDB->deleteFile($_GET['fileid']);	

	localRedir("/resources/".$fileInfo['category']);
}
else if(isset($_GET['linkid']) && $_GET['linkid'])
{
	include("url.php");

	$urlDB = new url;
	$urlInfo = $urlDB->getUrl($_GET['linkid']);
	
	//delete url, security checks done within function
	$urlDB->deleteUrl($_GET['linkid']);	

	localRedir("/resources/".$urlInfo['category']);

}
else
{
	error(2);
}
