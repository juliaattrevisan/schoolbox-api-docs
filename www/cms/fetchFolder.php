<?php

/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
*							                                *
*  Script to put all resources from a folder into a zip     *
*  and send to client.                                      *
*         				                                    *
*							                                *
/************************************************************/

require_once("../include/include.php");
require_once("../include/session.php");
require_once('../include/pclzip.lib.php');
require_once('folder.php');


class ZipFolder {

function ZipFolder($folder) {
	$this->db = new dbManager;
	$this->folder = $folder;
	$this->tmpName = tempnam(ZIP_directory, 'zip');
	$this->tmpFolder = $this->tmpName.'dir';
	$this->tmpZip = $this->tmpName.'.zip';
	
	$folderHandler = new folder;
	$this->folderDetails = $folderHandler->getFolder($folder);
}


function getResources() {
	$query = "SELECT id, realname FROM file WHERE category = {$this->folder} AND deleted = 0";
	return $this->db->dbGetAllRows($query);
}

function sendZip() {
	$this->createZip();
	
	$dodgychars = "[^0-9a-zA-Z()_-]";
	$filename = preg_replace("/$dodgychars/","_",$this->folderDetails['name']);
	
	$filesize = filesize($this->tmpZip);
	header ("Content-Type: application/x-zip-compressed"); 
	header ("Content-Disposition: attachment; filename=\"".$filename.".zip\"");
	header ("Content-Length: $filesize"); 
	readfile($this->tmpZip);
	
	$this->cleanUp();
}

function createZip() {
	
	$archive = new PclZip($this->tmpZip);
	$fileList = $this->getResources();

	mkdir($this->tmpFolder);

	foreach($fileList as $file) {
		if(is_file(FILE_directory .'/'.$file['id'].'.dat')) {
			//if two files in folder have same name it will overwrite.. not cool
			copy(FILE_directory .'/'.$file['id'].'.dat', $this->tmpFolder.'/'.$file['realname']);
			$zipFiles[] = $this->tmpFolder.'/'.$file['realname'];
		}
	}
	
	$archive->create($zipFiles,PCLZIP_OPT_REMOVE_ALL_PATH);


}

function cleanUp() {
	unlink($this->tmpName);
	unlink($this->tmpZip);
	$this->removeDir($this->tmpFolder);
}

function removeDir($path) {
   // Add trailing slash to $path if one is not there
   if (substr($path, -1, 1) != "/") {
       $path .= "/";
   }
   foreach (glob($path . "*") as $file) {
       if (is_file($file) === TRUE) {
           // Remove each file in this Directory
           unlink($file);
       }
       else if (is_dir($file) === TRUE) {
           // If this Directory contains a Subdirectory, run this Function on it
           removeDir($file);
       }
   }
   // Remove Directory once Files have been removed (If Exists)
   if (is_dir($path) === TRUE) {
       rmdir($path);
   }
} 

}

$zip = new ZipFolder($_GET['folder']);
$zip->sendZip();

?>