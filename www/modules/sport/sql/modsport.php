<?php

//sport object...allows you to add/modify/delete sports from sport table

class sport
{

	function sport($name = "") 
	{
		//setup database
		$this->SQL_db = "sports";
		$this->mySQLdb = new DeprecatedDatabase($this->SQL_db);

		$this->campus = 1; 

		$name = addslashes($name);
		$this->sport = $name;
		$query = "select seasonType from season where current = 1 and campus = '".$this->campus."'";
		$this->mySQLdb->dbDoQuery($query);
		$stype = $this->mySQLdb->dbGetNextArray();
		$this->seasonType = $stype['seasonType'];
		if ($this->sport){
			$this->exists = $this->sportExists();
		}
	}

	function getSports($sid = "")
	{
		if ($sid)
			$whereParms = "AND sid = '".$sid."'";	
		$query = "	SELECT sid , name 	
				FROM sport 
				WHERE seasonType = '".$this->seasonType."'  
				".$whereParms." ORDER BY name
			"; 
                $this->mySQLdb->dbDoQuery($query);
		while ($sport = $this->mySQLdb->dbGetNextArray())
		{
			$sport['name'] = stripslashes($sport['name']);
			$sports[] = $sport;
		}
		return $sports;
	}

	function getTeams($sid)
	{
		$this->mySQLdb->dbChangeDb($this->SQL_db);
		$query = "SELECT section, tid, teamorder 
			  FROM team 
			  WHERE sid = '".$sid."'
			  ORDER BY teamorder
		";
		$this->mySQLdb->dbDoQuery($query);
		while ($team = $this->mySQLdb->dbGetNextArray())
		{
			$team['section'] = stripslashes($team['section']);
			$teamList[] = $team;
		}
		return $teamList;
	}

 	function getStudTeamList($tid)
	{
		$query = "SELECT studid 
			  FROM studteam
			  WHERE tid = '".$tid."'
			 ";
		$this->mySQLdb->dbDoQuery($query);
		while ($studid = $this->mySQLdb->dbGetNextArray())
		{	
			$students[] = $studid;
		}
		return $students;
	}	

	function getSportInfo($sid)
	{
		if($this->sportExists($sid))
		{
			$query = "SELECT team.tid, sport.name as sname, team.section as sect, team.teamorder as teamorder, staffteam.staffid, staffteam.pos 
				  FROM (team, sport)
				  LEFT JOIN staffteam ON team.tid = staffteam.tid
                                  WHERE team.sid = '".$sid."'
			  	  AND sport.sid = team.sid	
				  ORDER BY team.teamorder,team.tid, staffteam.pos
                        ";
			$this->mySQLdb->dbDoQuery($query);
			while ($info = $this->mySQLdb->dbGetNextArray())
			{
				$info['sname'] = stripslashes($info['sname']);
				$info['sect'] = stripslashes($info['sect']);
				$array[] = $info;
			}
			return $array;
		}
	}

	function addSport($sportType)
	{
		if(!$this->exists)
		{
			$query = "SELECT * FROM sport 
			 	  WHERE name = '".$this->sport."' 
				  AND seasonType = '".$sportType."'";
			$this->mySQLdb->dbDoQuery($query);
			if ($this->mySQLdb->dbCountRows() == 0)
			{
				$query = "INSERT INTO sport 	(name, seasonType, campus) 
					  VALUES 		('".$this->sport."', '".$sportType."', '".$this->campus."')";
				$this->mySQLdb->dbDoQuery($query);
				return 1;
			}
		}
		else
			error(38);
	}
		

	function addTeam($id, $sect, $staffid1, $staffid2)
	{
		$sect = addslashes($sect);
		if ($this->sportExists($id))
		{
			$query = "SELECT teamorder FROM team WHERE sid = '".$id."' order by teamorder desc";
			$this->mySQLdb->dbDoQuery($query);
			if ($this->mySQLdb->dbCountRows() == 0)
				$highestorder = 1;
			else {
				$orderList = $this->mySQLdb->dbGetNextArray();
				$highestorder = $orderList['teamorder'] + 1;
			}
			$query = "INSERT INTO team (sid, section, teamorder) VALUES ('".$id."', '".$sect."', '".$highestorder."')";
			if ($this->mySQLdb->dbDoQuery($query))
			{
				$newid = $this->mySQLdb->dbInsertId(); 
				if ($staffid1)
				{
					$query = "INSERT INTO staffteam (pos, tid, staffid) VALUES (1, '".$newid."', '".$staffid1."')";
					$this->mySQLdb->dbDoQuery($query);
				}
				if ($staffid2)
				{				
					$query = "INSERT INTO staffteam (pos, tid, staffid) VALUES (2, '".$newid."', '".$staffid2."')";
					$this->mySQLdb->dbDoQuery($query);
				}
				return 1;	
			}
		}
		else
			print "sport doesn't exist";
	}
	
	function addStudToTeam($tid, $studid)
	{
		$query = "	SELECT * 
				FROM studteam
				WHERE tid = '".$tid."'
				AND studid = '".$studid."'
		";
		$this->mySQLdb->dbDoQuery($query);
		if ($this->mySQLdb->dbCountRows() == 0){
			$query = "INSERT INTO studteam
				  (studid, tid)
				  VALUES
				  ('".$studid."', '".$tid."')
				";
			$this->mySQLdb->dbDoQuery($query);
			return 1;
		}
	}	
		
	function modifySport($id)
  	{
		if($this->exists)
		{
			$query = "UPDATE sport SET name = '".$this->sport."' 
				  WHERE sid = '".$id."'
			";
			if($this->mySQLdb->dbDoQuery($query))
				return 1;
			else
				error(21);
		}
		else
			error(36);
	}	
	
	function modifyTeams($sid, $sect, $staffid1, $staffid2, $tid, $order)
	{	
		$sect = addslashes($sect);
		if ($this->sportExists($sid))
		{
			$query = "UPDATE team SET section = '".$sect."', teamorder = '".$order."' WHERE sid = '".$sid."' AND tid = '".$tid."'";
			$this->mySQLdb->dbDoQuery($query);
			
			$query = "SELECT * FROM staffteam WHERE tid = '".$tid."'"; 
			$result = $this->mySQLdb->dbDoQuery($query);
			$count = $this->mySQLdb->dbCountRows($result);
			while ($list = $this->mySQLdb->dbGetNextArray($result))	
			{
				if ($list['pos'] == 1 )
				{
					if ($staffid1)
					{
						$query = "UPDATE staffteam SET staffid = '".$staffid1."' 
							  WHERE tid = '".$tid."' 
					  		  AND pos = 1 ";
						$this->mySQLdb->dbDoQuery($query);
					}
					else
					{
						$query = "DELETE FROM staffteam  
							  WHERE tid = '".$tid."' 
					  		  AND pos = 1 ";
						$this->mySQLdb->dbDoQuery($query);
					}
					if ($count == 1 && $staffid2)
					{
						$query = "INSERT INTO staffteam (pos, tid, staffid) VALUES (2, '".$tid."', '".$staffid2."')";
						$this->mySQLdb->dbDoQuery($query);
					}
				}
				else if ($list['pos'] == 2) 
				{		
					if ($staffid2)
					{
						$query = "UPDATE staffteam SET staffid = '".$staffid2."' 
							  WHERE tid = '".$tid."' 
					  		  AND pos = 2 ";
						$this->mySQLdb->dbDoQuery($query);
					}
					else
					{
						$query = "DELETE FROM staffteam  
							  WHERE tid = '".$tid."' 
					  		  AND pos = 2 ";
						$this->mySQLdb->dbDoQuery($query);
					}
					if ($count == 1 && $staffid1)
					{
						$query = "INSERT INTO staffteam (pos, tid, staffid) VALUES (1, '".$tid."', '".$staffid1."')";
						$this->mySQLdb->dbDoQuery($query);
					}
				}
			}	
			if ($count == 0)
			{
				if ($staffid1)
				{
					$query = "INSERT INTO staffteam (pos, tid, staffid) VALUES (1, '".$tid."', '".$staffid1."')";
					$this->mySQLdb->dbDoQuery($query);
				}		
				if ($staffid2)
				{
					$query = "INSERT INTO staffteam (pos, tid, staffid) VALUES (2, '".$tid."', '".$staffid2."')";
					$this->mySQLdb->dbDoQuery($query);
				}
			}
		}	
		else
			print "sport doesn't exist";
	}

	function deleteSport($id)
	{
		if($this->sportExists($id))
		{
			$query = "SELECT tid FROM team 
				    WHERE sid = '".$id."'
			";
			if ($this->mySQLdb->dbDoQuery($query))
			{
				while ($tid = $this->mySQLdb->dbGetNextArray())
				{
					$query = "DELETE FROM staffteam 
						  WHERE tid = '".$tid[0]."' 
					";
					$this->mySQLdb->dbDoQuery($query);
				}
			}
			$query = "DELETE FROM team
				  WHERE team.sid = '".$id."'
			"; 	
			$this->mySQLdb->dbDoQuery($query);

			$query = "DELETE FROM sport 
				  WHERE sport.sid = '".$id."' 
			";	
			$this->mySQLdb->dbDoQuery($query);
		  	return 1;	
		}
	}
		
	function deleteTeam($id, $tid)
	{
		if ($this->sportExists($id))
		{
			//delete from team table
			$query = "DELETE FROM team 
				  WHERE tid = '".$tid."'
			";
			$this->mySQLdb->dbDoQuery($query);

			//delete from staffteam table
			$query = "DELETE FROM staffteam
				  WHERE tid = '".$tid."'
			";	
			$this->mySQLdb->dbDoQuery($query);
		}	
		else {
			print "not deleting";
			return 0;
		}
	}

	function deleteStudFromTeam($tid, $studid)
	{
		$query = "DELETE FROM studteam
			  WHERE tid = '".$tid."'
			  AND studid = '".$studid."'
		";
		if ($this->mySQLdb->dbDoQuery($query))
			return 1;
		else
			return 0;	
	}
	
	function sportExists($id="")
	{
		if ($id == "")
		{
			if ($this->sport)
			{
				$query = "SELECT * FROM sport 
					  WHERE name = '".$this->sport."' 
					  AND seasonType = '".$this->seasonType."'";
		
				$this->mySQLdb->dbDoQuery($query);
				if ($this->mySQLdb->dbCountRows() > 0)
				{
					return 1;
				}
				else
				{
					return 0;
				}
			}
			else
			{
				return 0;	  
			}
		}
		else
		{
			$query = "SELECT * FROM sport WHERE sid = '".$id."'";
		
			$this->mySQLdb->dbDoQuery($query);
			if ($this->mySQLdb->dbCountRows() > 0)
			{
				return 1;
			}
			else
			{
				return 0;
			}
		}
	}
}
?>
