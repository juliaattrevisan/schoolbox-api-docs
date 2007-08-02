<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//this script outputs an image of the user

require_once( 'include/helpDeskPage.php');
$page = new HelpDeskPage;
$page->authCheck(1);

if(isset($_GET['id']) && $_GET['id'])
{
	//get file details
	$db = mssql_connect(DB_HOST_SYNERGY,DB_USER_SYNERGY,DB_PASSWD_SYNERGY);	

	$db = mssql_connect('synergy', 'intranet', 'Qu4LiTy');

	//needs to check file permissions otherwise we have security problem
	$query = "	SELECT vPhotos.ID, vPhotos.Photo, vPhotos.PhotoType
				FROM SynergyOne.dbo.vPhotos vPhotos
				WHERE vPhotos.ID = ".$_GET['id'];
	$result = mssql_query($query, $db);
	if(mssql_num_rows($result) == 1)
	{
		$image = mssql_fetch_array($result);
		header("Content-Type: {$image['PhotoType']}");
		header ("Content-Disposition: filename={$_GET['id']}.jpg"); 
		//header ("Content-Length: $filesize");	
		echo $image['Photo'];
	}
} 

?>
