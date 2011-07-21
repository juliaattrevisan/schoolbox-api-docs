<?php
require_once('detNav.php');
class detSearch
{
	function detSearch()
	{
		$this->t_body = new Template(TPL_directory);
		$this->db = new DeprecatedDatabase();
		$this->db->dbConnect();
	}

	function displayFindStud()
	{
		//display search for user page
		$this->t_body->set_file("tplBODY", "modules/detention/findStud.htm");
		$this->t_body->set_var("TITLE", "Detention System - Student Search");
		$this->t_body->set_var("NAV", "");

		// Mark Parncutt 20 Jan 2005
		$nav = new detNav();
		$this->t_body->set_var("DETNAV", $nav->getNav());
//		if ( permType() > 2)
//			$this->t_body->set_var("ACTION", "<a href=\"viewDet.php\"><b>View Detentions</b></a>&nbsp;/&nbsp;<a href=\"attend.php\"><b>Attendances</b></a>");
//		else if (permType() == 2)
//			$this->t_body->set_var("ACTION", "<a href=\"attend.php\"><b>Attendances</b></a>");
//		else	
//			$this->t_body->set_var("ACTION", "");

		include_once("../../include/page.php");	
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function displayStudList($students)
	{
		$this->t_body->set_file("tplBODY", "modules/detention/listStud.htm");
		$this->t_body->set_var("TITLE", "MGS Portal - Find Student");
		$this->t_body->set_var("ACTION", "<a href=\"modifyDet.php\"><b>Back to Search</b></a>");
		include_once("../../include/page.php");	
		$this->t_body->set_block("tplBODY", "userlist", "user_block");
		
		$nav = new detNav();
		$this->t_body->set_var("DETNAV", $nav->getNav());
		
		foreach($students as $student)
		{
			$this->t_body->set_var("FIRSTNAME", $student['firstname']);
			$this->t_body->set_var("LASTNAME", $student['lastname']);
			$this->t_body->set_var("EMAIL", "<a href=\"/mail/send.php?replyto=
						".$student['username']."@".MAIL_address."\">
                                                ".$student['username']."@".MAIL_address."</a>");
			$this->t_body->set_var("YRLVL", $student['year']);
			$this->t_body->set_var("ID", $student['id']);
			$this->t_body->set_var("NAME", $student['firstname']." ".$student['lastname']);
			$this->t_body->parse("user_block", "userlist", true);
		}
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
		
	function searchStud($name, $yrlvl)
	{
		//search for a user based on at least one of the above fields
		//assumes at least one contains something
	
		$query = "SELECT id, username, firstname, lastname, year FROM user WHERE group_id in (3,4) AND year in (9,10,11,12)";
		if ($yrlvl != 0){
			$query .= "  AND year = '".$yrlvl."'";
		}
		$brace = "";
		if ($name){
			$namarr = explode(" ", $name);
			$first = 1;
			foreach ($namarr as $nam){
				if($nam != ""){
					if ($first)
						$query .= " AND (firstname LIKE '%".$nam."%' OR lastname LIKE '%".$nam."%'";
					else
						$query .= " OR firstname LIKE '%".$nam."%' OR lastname LIKE '%".$nam."%'";
				$first = 0;
				}
				$brace = ")";
			}
		}
		$query .= " $brace ORDER BY lastname, firstname";
		$this->db->query($query);
		//for each user put id in an array and return it.
		if($this->db->countRows() > 0)
		{
			while($student = $this->db->getNextArray())
			{
				$returnArray[] = $student;
			}
			return $returnArray;
		} 
		else
			error(31);
	}
}
?>
