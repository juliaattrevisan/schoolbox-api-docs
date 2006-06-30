<?
require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/adminonly.php");
require_once("../include/permission.php");
require_once("../calendar/calendar.php");

class fixEvents
{
	function fixEvents()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/fixEvents.htm");
		$this->message = "";
		$this->cal = new calendar();
		$this->db = new dbManager();
		$this->dbUpdate = new dbManager();
		$groupArray = $this->getAllGroups();
		$allCampusArray = $this->getAllCampus();		
		$sql = "SELECT id, sid FROM event WHERE access = 3 AND sid IS NOT NULL";
		$this->db->dbDoQuery($sql);
		
		while($row = $this->db->dbGetNextArray())
		{

			if($row['sid'] == 0)
			{
				$campusArray = $allCampusArray;
			}
			else
			{
				$campusArray[] = $row['sid'];
			}
			
			$this->addPerms($row['id'], $groupArray, $campusArray);
			$sql = "UPDATE event SET sid = null WHERE id = '{$row['id']}'";
			$this->dbUpdate->dbDoQuery($sql);
			$this->message .= "Updated event $id<br />";
		
		}
		
		$this->t_body->set_var("RESULT",$message);
		include_once("../include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
	
	function getAllGroups()
	{
		$sql = "SELECT id FROM groups";
		$this->db->dbDoQuery($sql);
		while($row = $this->db->dbGetNextArray())
		{
			$groups[] = $row['id'];	
		}
		return $groups;	
	}
	
	function getAllCampus()
	{
		$sql = "SELECT id FROM luCampus";
		$this->db->dbDoQuery($sql);
		while($row = $this->db->dbGetNextArray())
		{
			$campus[] = $row['id'];	
		}
		return $campus;	
	}
	
	function addPerms($id, $groupArray, $campusArray)
	{
		foreach($campusArray as $campus)
		{
			$this->cal->insertCampus($id, $campus);
			$this->message .= "Inserted campus $campus for event $id<br />";
		}

		foreach($groupArray as $group) 
		{
			$this->cal->insertGroup($id, $group);
			$this->message .= "Inserted group $group for event $id<br />";
		}
	}		
}

$page = new fixEvents();


?>