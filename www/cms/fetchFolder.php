<?php

/************************************************************
*                                                           *
*         Copyright (c) 2002-2008 Lektec Consulting         *
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
require_once('../include/permission.php');
require_once('folder.php');


class ZipFolder {

function ZipFolder($folder) {
	$this->db = new dbManager;
	$this->folder = $folder;
	$this->tmpName = tempnam(ZIP_directory, 'zip');
	$this->tmpFolder = $this->tmpName.'dir';
	$this->tmpZip = $this->tmpName.'.zip';
	$this->allowedChars = "[^0-9a-zA-Z()_-]";
	$this->size = 0;
	$this->maxDepth = 5;
	$this->maxSize = 262144000; //250MB
	
	
	$folderHandler = new folder;
	$this->folderDetails = $folderHandler->getFolder($folder);
	
	if(perm($this->folderDetails['id'], 1) < 1) {
		error(5);
	}
}


function getResources($folder) {
	$query = "SELECT id, realname, file_size FROM file WHERE category = {$folder} AND deleted = 0";
	return $this->db->dbGetAllRows($query);
}

function getFolders($folder) {
	$query = "SELECT id, name FROM category WHERE parent = {$folder}";
	return $this->db->dbGetAllRows($query);
}

function cleanName($name) {
	$filename = preg_replace("/{$this->allowedChars}/","_",$name);
	return $filename;
}

function uniqueFileName($fileName, $depth=1) {
	if(is_file($fileName)) {
		$pathParts = pathinfo($fileName);
		//because only php 5.2 has filename we need to do our own cutting and pasting to get the filename on its own 
		$pathParts['filename'] = substr($pathParts['basename'],0 ,strlen($pathParts['basename'])-strlen($pathParts['extension'])-1);
		$newName = $pathParts['dirname'].'/'.$pathParts['filename'].'_'.$depth.'.'.$pathParts['extension'];
		return $this->uniqueFileName($newName, ++$depth);	
	}
	else
		return $fileName;
}

function sendZip() {
	if($this->createZip()) {
	
		$filename = $this->cleanName($this->folderDetails['name']);
		
		$filesize = filesize($this->tmpZip);
		header ("Content-Type: application/x-zip-compressed"); 
		header ("Content-Disposition: attachment; filename=\"".$filename.".zip\"");
		header ("Content-Length: $filesize"); 
		readfile($this->tmpZip);
		
		$this->cleanUp();
	}
	else
		error(49);
}

function createDirectory($currentDir, $folderID, $depth=0) {		
	//populate dir with files
	$fileList = $this->getResources($folderID);
	
	if(is_array($fileList)) {
		foreach($fileList as $file) {
			$fileLocation = FILE_directory .'/'.$file['id'].'.dat';
			if(is_file($fileLocation)) {
				$this->size = $this->size + $file['file_size'];
				$fileName = $currentDir.'/'.$file['realname'];
				$fileName = $this->uniqueFileName($fileName); //ensure filename is unique else puts numbers in filename
				copy(FILE_directory .'/'.$file['id'].'.dat', $currentDir.'/'.$file['realname']);
			}
		}
	}
	
	//dont recurse deeper than max depth or max size	
	if($this->maxDepth > $depth && $this->maxSize > $this->size) {
	
		//get subfolders and recurse through them
		$folderList = $this->getFolders($folderID);
		if(is_array($folderList)) {
			foreach($folderList as $folder) {
				if(perm($folder['id'], 1) > 0)
				{
					$folderName = $this->cleanName($folder['name']);
					$folderLocation = $currentDir.'/'.$folderName;
					mkdir($folderLocation);
					$this->createDirectory($folderLocation, $folder['id'], ++$depth);
				}
			}
		}
	}
}

function createZip() {
	
	$archive = new PclZip($this->tmpZip);
	mkdir($this->tmpFolder);
	$this->createDirectory($this->tmpFolder, $this->folder);
	$archive->create($this->tmpFolder.'/',PCLZIP_OPT_REMOVE_PATH, $this->tmpFolder);
	return true;

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
           $this->removeDir($file);
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