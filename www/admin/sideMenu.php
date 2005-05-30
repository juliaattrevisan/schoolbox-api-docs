<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/


/* Class defines interface between the menu database and the administration software.   *
 * The following actions can be performed; delete, add, update, return 			*/

class sideMenu
{
	function sideMenu()
        {
                //always gonna need the db so lets create one..
                $this->db = new dbManager();
                $this->db->dbConnect();
        }

	function addMenuItem($parent, $text, $url, $target, $campus, $group)
	{
		$query = "INSERT INTO sideMenu (parent, txt, url, target) VALUES ('$parent', '$text', '$url', '$target')";
		$this->db->dbDoQuery($query);
		
		$id = $this->db->dbInsertId();

		$this->addPermissions($id, $campus, $group);
	}

	function updateMenuItem($id, $parent, $text, $url, $target, $campus, $group)
	{
		$query = "UPDATE sideMenu SET parent = '$parent', txt='$text', url='$url', target='$target' WHERE id = '$id'";
		$this->db->dbDoQuery($query);

		$this->deletePermissions($id);
		$this->addPermissions($id, $campus, $group);
	
	}

	function deleteMenuItem($id)
	{
		$query = "DELETE FROM sideMenu WHERE id = '$id'";
		$this->db->dbDoQuery($query);
		$this->deletePermissions($id);
	}

	function getMenuItem($id)
	{
		$query = "SELECT * FROM sideMenu WHERE id = '$id'";
		return $this->db->dbGetSingleRow($query);
	}

	function getMenuItemCampusList($id)
	{
		$query = "SELECT * FROM sideMenuCampus WHERE id = '$id'";
		return $this->db->dbGetAllRows($query);
	}

	function getMenuItemGroupList($id)
	{
		$query = "SELECT * FROM sideMenuGroup WHERE id = '$id'";
		return $this->db->dbGetAllRows($query);
	}



	function addPermissions($id, $campus, $group)
	{

                if(is_array($campus))
                {
                        foreach($campus as $campusID)
                        {
				$this->insertCampus($id, $campusID);
                        }
                }
		
                if(is_array($group))
                {
                        foreach($group as $groupID)
                        {
				$this->insertGroup($id, $groupID);
                        }
                }
		

	
	}

	function deletePermissions($id)
	{
		$query = "DELETE FROM sideMenuCampus WHERE sideMenuCampus.id = '$id'";
		$this->db->dbDoQuery($query);
		$query = "DELETE FROM sideMenuGroup WHERE sideMenuGroup.id = '$id'";
		$this->db->dbDoQuery($query);
	}

	function insertCampus($id, $campus)
	{
		$query = "INSERT INTO sideMenuCampus (id, campus) VALUES ('$id', '$campus')";
		$this->db->dbDoQuery($query);
	}

	function insertGroup($id, $group)
	{
		$query = "INSERT INTO sideMenuGroup (id, gid) VALUES ('$id', '$group')";
		$this->db->dbDoQuery($query);

	}

}

?>
