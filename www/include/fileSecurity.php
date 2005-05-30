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

require_once("fileTypes.php");

//check error codes in FILE array
switch($_FILES['file']['error'])
{
	case 0: //no error
		break;
	case 1: //uploaded file exceeds the upload_max_filesize directive in php.ini
		error(10);
      		break;
	case 2: //uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the html form
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
if(!is_uploaded_file($_FILES['file']['tmp_name']))
	error(9, "Security infringement, possible file upload attack.");

//file size test
if( $_FILES['file']['size'] <= 0)
	error(10);

//check file type is in allowed file type array
if(!isset($allowedFileTypes[strtolower($_FILES['file']['type'])]))
	error(11, "Your file, ".$_FILES['file']['name']." with the extension .$ext and type of ".$_FILES['file']['type']);

//check file has correct and allowed extension for its identified mime type
$flag = 0;
foreach($allowedFileTypes[strtolower($_FILES['file']['type'])][2] as $allowedExt)
{
	if(strpos(strtolower($_FILES['file']['name']), ".".$allowedExt) !== FALSE)
	{
		$flag = 1;
		break;
 	}
}
if(!$flag)
{
	list($name, $ext) = explode(".",$_FILES['file']['name']);
	error(11, "Your file, ".$_FILES['file']['name']." with the extension .$ext and type of ".$_FILES['file']['type']);
}
?>
