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
require_once("include/session.php");
require_once("include/include.php");
require_once("include/permission.php");
	
if(isset($_GET['id']) && $_GET['id'])
{
	//get file details
	$db = new dbManager;
	$db->dbConnect();

	if(!isset($_GET['myFile'])){
		//needs to check file permissions otherwise we have security problem
		$query = "SELECT realname, mime_type, category FROM file WHERE id = '".$_GET['id']."'";
	}else{
		$query = "SELECT * FROM myFile WHERE id = '".$_GET['id']."'";
	}
	$db->query($query);
	
	if($db->countRows() < 1)
		error(2);
	else
	{
		if($_GET['save'])
			$attach = "attachment;";
		else
			$attach = "";
		
		$resultArray = $db->getNextArray();

		//check perms to double check security
		if(isset($_GET['myFile'])){
			if($resultArray['owner'] == $_SESSION['id']){
				//all checks completed.. continue with file send
				$filename = MYFILE_directory ."/". $_GET['id'] . ".dat";
	
				$filesize = @filesize($filename);
				if($filesize == FALSE) //if we cannot find the file (something is wrong dont link to it)
					error(41);
	
				//send headers
				if($resultArray['mime_type'])		
					header ("Content-Type: {$resultArray['mime_type']}"); 
				else
					header ("Content-Type: application/octet-stream"); 

				header ("Pragma: ");
				header ("Cache-control: ");			
				header ("Content-Disposition: $attach filename=\"".$resultArray['realname']."\""); 
				header ("Content-Length: $filesize");	
				readfile($filename);
			}
		}
		elseif(perm($resultArray['category'], 1) > 0)
		{
			//all checks completed.. continue with file send
			$filename = FILE_directory ."/". $_GET['id'] . ".dat";

			$filesize = @filesize($filename);
			if($filesize == FALSE) //if we cannot find the file (something is wrong dont link to it)
				error(41);

			//send headers
			if($resultArray['mime_type'])		
				header ("Content-Type: {$resultArray['mime_type']}"); 
			else
				header ("Content-Type: application/octet-stream"); 

			header ("Pragma: ");
			header ("Cache-control: ");		
			header ("Content-Disposition: $attach filename=\"".$resultArray['realname']."\""); 
			header ("Content-Length: $filesize");	
			readfile($filename);
		}
		else
		{
			error(5);
		}
	}

} 

?>
