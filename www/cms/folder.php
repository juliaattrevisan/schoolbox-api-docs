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

		if($code)
			$query = "INSERT INTO category (name, parent, template, code) VALUES ('$name', '$parent', '$template', '$code')";
		else
			$query = "INSERT INTO category (name, parent, template) VALUES ('$name', '$parent', '$template')";
                
		$this->db->dbDoQuery($query);
		$cid = $this->db->dbInsertId();
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

		if($code)
			$query = "UPDATE category SET name = '$name', parent = '$parent', template = '$template', code = '$code' WHERE id = '$cid'";
		else
			$query = "UPDATE category SET name = '$name', parent = '$parent', template = '$template' WHERE id = '$cid'";
                
		$this->db->dbDoQuery($query);
                $this->createTemplate($cid, $template);
		
		return $cid;
	}

	function deleteCat($id)
	{
		//do security and child check before removing folder and related info
		$folderInfo = $this->getFolder($id);
		$this->checkWriteAccess($folderInfo['parent']);
		$this->checkForChildren($id);

		$query = "DELETE FROM category WHERE id = '".$id."'";
		$this->db->dbDoQuery($query);

		$query = "DELETE FROM homepage_comp_location WHERE cid = '".$id."'";
		$this->db->dbDoQuery($query);

		$query = "DELETE FROM homepage_config WHERE cid = '".$id."'";
		$this->db->dbDoQuery($query);

		$query = "DELETE FROM userAccess WHERE service = '1' AND sid = '".$id."'";
		$this->db->dbDoQuery($query);

		$query = "DELETE FROM forum WHERE cid = '".$id."'";
		$this->db->dbDoQuery($query);

		$query = "DELETE FROM subject_news WHERE code = '".$id."'";
		$this->db->dbDoQuery($query);

	}

	function deletePerms($cid)
	{
		$query = "DELETE FROM perms WHERE cid = '".$cid."'";
		$this->db->dbDoQuery($query);
	}

	function insertPerm($cid, $group, $access)
	{
		$query = "INSERT INTO perms (cid, gid, type) VALUES ('$cid','$group','$access')";
		$this->db->dbDoQuery($query);
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
		{
			//children exist exit with error
			error(12);
		}
		
		//check for files/links in category
		$query = "SELECT id FROM link WHERE category = '".$id."'";
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
		{
			//children exist exit with error
			error(12);
		}
		
		$query = "SELECT id FROM file WHERE category = '".$_GET['delete']."' AND deleted = '0'";
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
		{
			//children exist exit with error
			error(12);
		}
		
		//check for submission boxes
		//TODO: check for submission boxes

	}
}
?>
