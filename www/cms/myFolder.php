<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

/* Class defines interface between category/folder database and software.         *
 * The following actions can be performed; delete, add, update, get */

class myFolder
{
	function myFolder()
	{
		//always gonna need the db so lets create one..
		$this->db = new dbManager();
	}

	function insertCat($name, $parent)
	{  
  		$myFolder = array(	'name'			=> $name,
							'parent'	 	=> $parent,
							'owner'			=> $_SESSION['id']
								);
		
		$this->db->dbInsertRow('myFolder', $myFolder);
  
		$catid = $this->db->dbInsertId();
		
		$logEntry = array(	'object'			=> 'myFolder',
										'objectId'		=> $catid,
										'action'			=> 'create',
										'user'			=> $_SESSION['id'],
							);
		
		$this->db->dbInsertRow('cmsLog', $logEntry);
	
		return $catid;
	}

	function modifyCat($catid, $name, $parent)
	{		
		//update folder details
		$cat = array('id'=>$catid, 'name'=>$name, 'parent'=>$parent);
					
		$this->db->dbUpdateRow('myFolder', 'id', $cat);
		
		//log modification
		$log = array(	'object'		=>'myFolder', 
								'objectId'	=>$catid, 
								'action'		=>'modify', 
								'user'		=>$_SESSION['id']
							);
		$this->db->dbInsertRow('cmsLog', $log);
				
		return $catid;
	}

	function deleteCat($catid)
	{
		//do security and child check before removing folder and related info
		$folderInfo = $this->getFolder($catid);
		$this->checkForChildren($catid);
		
		$this->db->dbDeleteRow('myFolder', 'id', $catid);
						
		$logEntry = array(	'object'			=> 'myFolder',
										'objectId'		=> $id,
										'action'			=> 'delete',
										'user'			=> $_SESSION['id'],
							);
		
		$this->db->dbInsertRow('cmsLog', $logEntry);

	}

	function getFolder($catid)
	{
		$query = "SELECT * FROM myFolder WHERE id = '$catid'";
		return $this->db->dbGetSingleRow($query);
	}

	function checkForChildren($id)
	{
		//check for child folders
		$query = "SELECT id FROM myFolder WHERE parent = '".$id."'";
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
			error(12); //children exist exit with error
		
		//check for files in category
		$query = "SELECT id FROM myFile WHERE parent = '".$id."' AND deleted = '0'";
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
			error(12); //children exist exit with error

	}
}
?>
