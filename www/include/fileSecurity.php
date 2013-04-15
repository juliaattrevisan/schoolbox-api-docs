<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	This file does standard security and error checks on	* 
*	file uploads. Just include the file and it will do all	* 
*	basic tests required. Used in modResource.php, 			*
*	submitWork.php and returnWork.php 						*
*   $Id: fileSecurity.php,v 1.3 2004/04/27 05:16:48 jleckie Exp $
*                                                           *
************************************************************/

function fileSecurityCheck($error, $file_name, $tmp_name, $size, $type="")
{

	//check error codes in FILE array
	switch($error)
	{
		case 0: //no error
			break;
		case 1: //uploaded file exceeds the upload_max_filesize directive in php.ini
			error(10);
				break;
		case 2: //uploaded file exceeds the Cfg::get('max_file_size') directive that was specified in the html form
			error(10);
			break;
		case 3: //uploaded file was only partially uploaded
			error(43);
			break;
		case 4: //no file was uploaded
			error(43);
			break;
		default: //a default error, just in case!  :)
			break;
	}

	//check file is in upload dir
	if(!is_uploaded_file($tmp_name))
		error(9, "Security infringement, possible file upload attack.");
	
	//file size test
	if( $size <= 0)
		error(10);
	
	// MIME type check
	if($type)
	{
		//$mime = strtolower($type);
		//improved so that it only gets the last . in the string
		$ext = substr(strrchr($file_name, "."), 1);
		
		//make sure we can read the magic file and fileinfo functions are defined
		if(is_readable(MIME_magic) && function_exists('finfo_open') ) {
			//update to check against magic mime file on server rather than trust browser interpretation
			$handle = finfo_open(FILEINFO_MIME, MIME_magic);
			$mime = finfo_file($handle,$tmp_name);
		}
		else //just trust browser not as good but it works
			$mime = strtolower($type);
		
		$db = DeprecatedDatabase::getInstance();
		$query = "SELECT t.fileTypeID FROM allowedFileTypes t LEFT JOIN allowedFileExtension e on t.fileTypeID = e.fileTypeID WHERE mime = '$mime' AND extension = '$ext' AND enable = 'true'";
		if(!$db->getRow($query))
			error(11, "Your file, ".$_FILES['file']['name']." with the extension '$ext' and type of '$mime'");
	}
}
?>