<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

/* Class defines interface between category/folder database and software.         *
 * The following actions can be performed; delete, add, update, get */

class folder
{
	function folder()
	{
		//always gonna need the db so lets create one..
		$this->db = new dbManager();
	}
	
	function checkWriteAccess($category)
	{
		require_once("../include/permission.php");
		
		//just a simple cache to stop excessive calls to perm (which is quite heavy)
		if(!isset($this->permCache[$category]))
		{
			$access = perm($category, HOMEPAGESERVICE);
			$this->permCache[$category] = $access;
		}
		else
		{
			$access = $this->permCache[$category];
		}
		
		if($access < 2)
				error(6);
		
		$this->permCache[$category] = $access;
		
		return $access;
	}
	

	function insertCat($name, $parent, $template = 0, $code = 0)
	{
		$this->checkWriteAccess($parent);
  
  		$folder = array(	'name'			=> $name,
							'parent'	 	=> $parent,
							'template'		=> $template,
						);
		
		if($code)
			$folder['code']	= $code;
		
		$this->db->dbInsertRow('category', $folder);
  
		$cid = $this->db->dbInsertId();
		
		$logEntry = array(	'object'		=> 'folder',
							'objectId'		=> $cid,
							'action'		=> 'create',
							'user'			=> $_SESSION['id'],
						);
		
		$this->db->dbInsertRow('cmsLog', $logEntry);
		
		$this->createTemplate($cid, $template);

		return $cid;
	}
	
	function insertPermArray($cid, $parent, $permArray)
	{
		$this->checkWriteAccess($parent);
	
		$query = "SELECT id FROM groups";
		$reshandle=$this->db->dbDoQuery($query);
		while($group = $this->db->dbGetNextArray($reshandle) )
		{
			//setup variable names
			$vname = $group['id'];	
			if($permArray[$vname])
			{
				$this->insertPerm($cid, $group['id'], $permArray[$vname]);
			}
		}
	}

	function modifyCat($cid, $name, $parent, $template = 0, $code = 0)
	{
		$this->checkWriteAccess($parent);

		//update folder details
		$cat = array('id'=>$cid, 'name'=>$name, 'parent'=>$parent);
		if($code)
			$cat['code'] = $code;
		if($template)
			$cat['template'] = $template;
			
		$this->db->dbUpdateRow('category', 'id', $cat);
		
		//log modification
		$log = array(	'object'		=> 'folder', 
						'objectId'		=> $cid, 
						'action'		=> 'modify', 
						'user'			=> $_SESSION['id']
					);
		$this->db->dbInsertRow('cmsLog', $log);
		
		//if template set apply new template
		if($template)
			$this->createTemplate($cid, $template);
		
		return $cid;
	}

	function deleteCat($id)
	{
		//do security and child check before removing folder and related info
		$folderInfo = $this->getFolder($id);
		$this->checkWriteAccess($folderInfo['parent']);
		$this->checkForChildren($id);

		
		$this->db->dbDeleteRow('category', 'id', $id);
		$this->db->dbDeleteRow('homepage_comp_location', 'cid', $id);
		$this->db->dbDeleteRow('homepage_config', 'cid', $id);
		$this->db->dbDeleteRow('forum', 'cid', $id);
		$this->db->dbDeleteRow('subject_news', 'code', $id);
		$this->db->dbDeleteRow('userGroups', 'cid', $id);
		
		$this->deletePerms($cid);
		$query = "DELETE FROM userAccess WHERE service = '1' AND sid = '".$id."'";
		$this->db->dbDoQuery($query);
		
		$logEntry = array(	'object'		=> 'folder',
							'objectId'		=> $id,
							'action'		=> 'delete',
							'user'			=> $_SESSION['id'],
						);
		
		$this->db->dbInsertRow('cmsLog', $logEntry);

	}

	function deletePerms($cid)
	{
		$this->db->dbDeleteRow('perms', 'cid', $cid);
	}

	function insertPerm($cid, $group, $access)
	{
		$perm = array(	'cid'		=> $cid,
						'gid'		=> $group,
						'type' 	=> $access,
					);
		$this->db->dbInsertRow('perms', $perm); 
	}

	function getFolder($cid)
	{
		$query = "SELECT name, parent, code, template FROM category WHERE id = '$cid'";
		return $this->db->dbGetSingleRow($query);
	}

	function getPerms($cid, $gid)
	{
		$query = "SELECT type FROM perms WHERE cid='$cid' AND gid='$gid'";
		$perm = $this->db->dbGetSingleRow($query);
		return $perm['type'];
	}

	function createTemplate($cid, $template)
	{
		require_once("../subject/modPage.php");

		$templateMod = new modPage($cid);

		if( $template == 1)
			$templateMod->createSubjectPage();
		else if($template == 2)
			$templateMod->createGroupPage();
		else if($template == 3)
			$templateMod->createGroupForumPage();
		else if($template == 4)
			$templateMod->createFacultyPage();
 		else if($template == 5)
                        $templateMod->createSubjectForumPage();
                else if($template == 6)
			$templateMod->createGroupForumPage();
	}

	function checkForChildren($id)
	{
		//check for child folders
		$query = "SELECT id FROM category WHERE parent = '".$id."'";
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
			error(12); //children exist exit with error
		
		//check for files/links in category
		$query = "SELECT id FROM link WHERE category = '".$id."'";
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
			error(12); //children exist exit with error
		
		$query = "SELECT id FROM file WHERE category = '".$id."' AND deleted = '0'";
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
			error(12); //children exist exit with error
		
		//check for submission boxes TODO: REMOVE THIS AND PUT A CLEANUP SUBMISSION BOX FUNCTION IN (but maybe only for superusers), also add recursive delete functions)
		//$query = "SELECT id FROM submission_box WHERE category = $id AND deleted = 0";
		$query = "UPDATE submission_box SET deleted = 1 WHERE category = $id";
		$this->db->dbDoQuery($query);

	}

}
?>
