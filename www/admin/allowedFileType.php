<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/


/* Class defines interface between the allowed files database and the administration software.   *
 * The following actions can be performed; delete, add, update, return 			*/

class allowedFileType
{
	function allowedFileType()
	{
		//always gonna need the db so lets create one..
		$this->db = new dbManager();
		$this->db->dbConnect();
	}

	function addFileType($description, $mime, $icon, $inline, $enable, $extensions)
	{
		$query = "INSERT INTO allowedFileTypes (description, mime, iconFile, inline, enable) VALUES ('$description', '$mime', '$icon', '$inline', '$enable')";
		$this->db->dbDoQuery($query);
		
		$id = $this->db->dbInsertId();

		$this->addExtensions($id, $extensions);
	}

	function updateFileType($id, $description, $mime, $icon, $inline, $enable, $extensions)
	{
		$query = "UPDATE allowedFileTypes SET description = '$description', mime='$mime', iconFile='$icon', inline='$inline', enable='$enable' WHERE fileTypeID = '$id'";
		$this->db->dbDoQuery($query);

		$this->deleteExtensions($id);
		$this->addExtensions($id, $extensions);	
	}
	

	function deleteFileType($id)
	{		
		$this->deleteExtensions($id);
		$query = "DELETE FROM allowedFileTypes WHERE fileTypeID = $id";
		$this->db->dbDoQuery($query);
	}

	function getFileType($id)
	{
		$query = "SELECT * FROM allowedFileTypes WHERE fileTypeID = $id";
		$fileType = $this->db->dbGetSingleRow($query);
		
		$query = "SELECT extension FROM allowedFileExtension WHERE fileTypeID = $id"; 
		$this->db->dbDoQuery($query);
		while($ext = $this->db->dbGetNextArray() ) {
			$fileType['extensions'][] = $ext['extension'];
		}
		return $fileType;
	}

	function getFileTypeList() {
		$query = "SELECT fileTypeID, description, iconFile, enable, mime FROM allowedFileTypes";
		return $this->db->dbGetAllRows($query);
	}


	function getIconList()
	{
		//get a list of filenames from mime
		$dir = $_SERVER['DOCUMENT_ROOT'].'/images/mime';
		$path = opendir($dir);
		while (false !== ($file = readdir($path))) {
			if($file!="." && $file!="..") {
				if(is_file($dir."/".$file))
					$files[]=$file;           
			}
		}
	
		if($files) {
			natcasesort($files);
		}
		closedir($path);

		return $files;
	}

	function addExtensions($fileTypeID, $extensions)
	{
		$extensions = str_replace('.','',$extensions);
		$extensionList = explode(',',$extensions);
		foreach($extensionList as $ext) {
			
			$query = "INSERT INTO allowedFileExtension (fileTypeID, extension) VALUES ($fileTypeID, '$ext')";
			$this->db->dbDoQuery($query);
		}
	}

	function deleteExtensions($id)
	{
		$query = "DELETE FROM allowedFileExtension WHERE fileTypeID = $id";
		$this->db->dbDoQuery($query);
	}

}

?>
