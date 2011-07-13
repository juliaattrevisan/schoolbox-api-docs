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
		$this->db = new Database();
		$this->db->dbConnect();
	}

	function addMenuItem($parent, $text, $url, $target, $campus, $group)
	{
		//fetch last item in list so we can insert this item after it
		$query = "SELECT sequence FROM sideMenu WHERE parent = $parent ORDER BY sequence DESC";
		$lastItem = $this->db->getRow($query);
		$sequence = $lastItem['sequence'] + 1;
		$text = scrubTEXT($text);
	
		$query = "INSERT INTO sideMenu (parent, txt, url, target, sequence) VALUES ($parent, '$text', '$url', '$target', $sequence)";
		$this->db->query($query);
		
		$id = $this->db->dbInsertId();

		$this->addPermissions($id, $campus, $group);
	}

	function updateMenuItem($id, $parent, $text, $url, $target, $campus, $group)
	{
		$text = scrubTEXT($text);
		$query = "UPDATE sideMenu SET parent = '$parent', txt='$text', url='$url', target='$target' WHERE id = '$id'";
		$this->db->query($query);

		$this->deletePermissions($id);
		$this->addPermissions($id, $campus, $group);
		$this->resequenceMenu($parent);
	
	}
	
	function moveMenuItemUp($id)
	{
		//fetch sequence of item below
		$item = $this->getMenuItem($id);
		$query = "UPDATE sideMenu SET sequence = (sequence + 1) WHERE parent = {$item['parent']} AND sequence = ({$item['sequence']} - 1)";
		$this->db->query($query);
		
		$query = "UPDATE sideMenu SET sequence = (sequence - 1) WHERE id = $id";
		$this->db->query($query);
	}
	
	function moveMenuItemDown($id)
	{
		//fetch sequence of item below
		$item = $this->getMenuItem($id);
		$query = "UPDATE sideMenu SET sequence = (sequence - 1) WHERE parent = {$item['parent']} AND sequence = ({$item['sequence']} + 1)";
		$this->db->query($query);
		
		$query = "UPDATE sideMenu SET sequence = (sequence + 1) WHERE id = $id";
		$this->db->query($query);
	}
	
	//This function resequences a portion of the menu to ensure that it stays in a sequential order
	//This function is called on updates (unecessary but will help occasionally) and deletes
	function resequenceMenu($parent)
	{
		$query = "SELECT id FROM sideMenu WHERE parent = $parent ORDER BY sequence, txt";
		$result = $this->db->query($query);
		
		for($i=0;$item = $this->db->getNextArray($result);$i++)
		{
			$seqquery = "UPDATE sideMenu SET sequence = $i WHERE id = {$item['id']}";
			$this->db->query($seqquery);
		}
	}

	function deleteMenuItem($id)
	{
		$item = $this->getMenuItem($id);
		
		$query = "DELETE FROM sideMenu WHERE id = '$id'";
		$this->db->query($query);
		$this->deletePermissions($id);
		
		$this->resequenceMenu($item['parent']);
	}

	function getMenuItem($id)
	{
		$query = "SELECT * FROM sideMenu WHERE id = '$id'";
		return $this->db->getRow($query);
	}

	function getMenuItemCampusList($id)
	{
		$query = "SELECT * FROM sideMenuCampus WHERE id = '$id'";
		return $this->db->getAll($query);
	}

	function getMenuItemGroupList($id)
	{
		$query = "SELECT * FROM sideMenuGroup WHERE id = '$id'";
		return $this->db->getAll($query);
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
		$this->db->query($query);
		$query = "DELETE FROM sideMenuGroup WHERE sideMenuGroup.id = '$id'";
		$this->db->query($query);
	}

	function insertCampus($id, $campus)
	{
		$query = "INSERT INTO sideMenuCampus (id, campus) VALUES ('$id', '$campus')";
		$this->db->query($query);
	}

	function insertGroup($id, $group)
	{
		$query = "INSERT INTO sideMenuGroup (id, gid) VALUES ('$id', '$group')";
		$this->db->query($query);

	}

}

?>
