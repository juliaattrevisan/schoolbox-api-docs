<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//this script outputs an image of the user

//includes
require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/staffonly.php");

//no image
define(NO_IMAGE, '../images/no_available.jpg');
	
if(isset($_GET['id']) && $_GET['id'])
{
	//get file details
	$db = mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);	

	//needs to check file permissions otherwise we have security problem
	$query = "	SELECT vPhotos.ID, vPhotos.Photo, vPhotos.PhotoType
				FROM SynergyOne.dbo.vPhotos vPhotos
				WHERE vPhotos.ID = {$_GET['id']}";
	$result = mssql_query($query, $db);
	if(mssql_num_rows($result) == 1)
	{
		$image = mssql_fetch_array($result);
		header("Content-Type: image/jpg");
		header("Content-Disposition: filename={$_GET['id']}.jpg"); 
		//header ("Content-Length: $filesize");	
		echo $image['Photo'];
	}
	else
	{
		if(file_exists(NO_IMAGE))
		{
			header("Content-Type: image/jpg");
			header("Content-Disposition: filename=nopic.jpg"); 
			readfile(NO_IMAGE);
		}		
	}
} 

?>
