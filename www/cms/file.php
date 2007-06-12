<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

/* Class defines interface between file database and software.         *
 * The following actions can be performed; delete, add, update */

class file
{
	function file()
	{
		//always gonna need the db so lets create one..
		$this->db = new dbManager();
	}

	function checkWriteAccess($category)
	{
		require_once("../include/permission.php");
		
		//dont allow write to root fullstop!!
		if($category == 0)
			error(2);

		if(perm($category, HOMEPAGESERVICE) < 2)
			error(6);
	} 

	function modifyFile($description, $comment, $category, $type, $modid)
	{
		//check allowed perms to add to specified category before proceeding
		$this->checkWriteAccess($category);		
		
		$file = array(	'id'				=> $modid,
								'description'	=> $description,
								'comment'	 	=> $comment,
								'category'		=> $category,
								'type'			=> $type,
							);
		
		$this->db->dbUpdateRow('file', 'id', $file);
		
		$logEntry = array(	'object'			=> 'file',
										'objectId'		=> $modid,
										'action'			=> 'modify',
										'user'			=> $_SESSION['id'],
							);
		
		$this->db->dbInsertRow('cmsLog', $logEntry);
		
	}

	function addFile($file_name, $file_type, $file_loc, $description, $comment, $category, $type)
	{
		//check allowed perms to add to specified category before proceeding
		$this->checkWriteAccess($category);		
		$filesize = @filesize($file_loc);
	
		$file = array(	'description'	=> $description,
								'comment'	 	=> $comment,
								'category'		=> $category,
								'owner'			=> $_SESSION['id'],
								'type'			=> $type,
								'realname'	=> $file_name,
								'submitted'	=> 'NOW()',
								'mime_type'	=> $file_type,
								'file_size'		=> $filesize,
							);
		
		$this->db->dbInsertRow('file', $file);
	
		//get file ID from INSERT
		$fileId = $this->db->dbInsertId();
	
		if( $file_loc )
		{
				//save file using file ID as name
				$newFileName = $fileId . ".dat";
				copy($file_loc, FILE_directory ."/". $newFileName);
		}
	
		$logEntry = array(	'object'			=> 'file',
										'objectId'		=> $fileId,
										'action'			=> 'create',
										'user'			=> $_SESSION['id'],
							);
		
		$this->db->dbInsertRow('cmsLog', $logEntry);
	
		return $fileId;
	
	}

	function deleteFile($id)
	{
		//check sanity and valid rights
		//check file exists. and user has nescessary rights
		$fileInfo = $this->getFile($id);
		if($fileInfo['category'])
			$this->checkWriteAccess($fileInfo['category']);	
		else
			error(41);

		//ok all checks completed

		//now delete file by updating it as deleted
		$this->db->dbUpdateRow('file', 'id', array('id'=>$id, 'deleted'=>1));

		$logEntry = array(	'object'			=> 'file',
										'objectId'		=> $id,
										'action'			=> 'delete',
										'user'			=> $_SESSION['id'],
							);
		
		$this->db->dbInsertRow('cmsLog', $logEntry);

		//we leave file incase we need it but we should clean up any cached files right now
		

	}

	function getFile($id)
	{
                $query = "SELECT category, description, comment, type FROM file WHERE id = '$id'";
                return $this->db->dbGetSingleRow($query);
	} 

}
?>
