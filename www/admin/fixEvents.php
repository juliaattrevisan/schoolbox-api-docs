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
		$this->db = new DeprecatedDatabase(SQL_db, 1);
		$groupArray = $this->getAllGroups();
		$allCampusArray = $this->getAllCampus();		
		$sql = "SELECT id, sid FROM event WHERE access = 3 AND sid IS NOT NULL";
		$result = $this->db->query($sql);
		
		while($row = $this->db->getNextArray($result))
		{
			$campusArray = array();
			if($row['sid'] == 0)
			{
				$campusArray = $allCampusArray;
			}
			else
			{
				$campusArray[] = $row['sid'];
			}
			
			$this->addPerms($row['id'], $groupArray, $campusArray);
			$sql = "UPDATE event SET sid = null WHERE id = {$row['id']}";
			$this->db->query($sql);
			$this->message .= "Updated event $id<br />";
		
		}
		
		$this->t_body->set_var("RESULT",$this->message);
		include_once("../include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
	
	function getAllGroups()
	{
		$sql = "SELECT id FROM groups";
		$this->db->query($sql);
		while($row = $this->db->getNextArray())
		{
			$groups[] = $row['id'];	
		}
		return $groups;	
	}
	
	function getAllCampus()
	{
		$sql = "SELECT id FROM luCampus";
		$this->db->query($sql);
		while($row = $this->db->getNextArray())
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
