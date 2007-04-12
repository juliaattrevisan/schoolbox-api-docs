<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

/* Class defines interface between file database and software.         *
 * The following actions can be performed; delete, add, update */

class myFile
{
	function myFile()
	{
		//always gonna need the db so lets create one..
		$this->db = new dbManager();
	}

	function modifyFile($parent, $description, $modid)
	{		
		$file = array(	'id'				=> $modid,
						'parent'		=> $parent,
						'description'	=> $description
					);
		
		$this->db->dbUpdateRow('myFile', 'id', $file);
		
		$logEntry = array(	'object'			=> 'myFile',
										'objectId'		=> $modid,
										'action'			=> 'modify',
										'user'			=> $_SESSION['id'],
							);
		
		$this->db->dbInsertRow('cmsLog', $logEntry);
		
	}

	function addFile($parent, $file_name, $file_type, $description)
	{
		$filesize = @filesize($_FILES['file']['tmp_name']);
	
		$file = array(	'description'	=> $description,
								'parent'		=> $parent,
								'owner'			=> $_SESSION['id'],
								'realname'	=> $file_name,
								'submitted'	=> 'NOW()',
								'mime_type'	=> $file_type,
								'file_size'		=> $filesize
							);
		
		$this->db->dbInsertRow('myFile', $file);
	
		//get file ID from INSERT
		$fileId = $this->db->dbInsertId();
	
		if( $_FILES['file']['tmp_name'] )
		{
				//save file using file ID as name
				$newFileName = $fileId . ".dat";
				copy($_FILES['file']['tmp_name'], MYFILE_directory ."/". $newFileName);
		}
	
		$logEntry = array(	'object'			=> 'myFile',
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

		//now delete file by updating it as deleted
		$this->db->dbUpdateRow('myFile', 'id', array('id'=>$id, 'deleted'=>1));

		$logEntry = array(	'object'			=> 'myFile',
										'objectId'		=> $id,
										'action'			=> 'delete',
										'user'			=> $_SESSION['id'],
							);
		
		$this->db->dbInsertRow('cmsLog', $logEntry);

		//we leave file incase we need it but we should clean up any cached files right now
		

	}

	function getFile($id)
	{
                $query = "SELECT * FROM myFile WHERE id = '$id'";
                return $this->db->dbGetSingleRow($query);
	} 

}

?>
