<?php

//season object...allows you to add/modify/delete seasons from season table


class season
{
	function season($snid = "") 
	{
		//setup database
		$SQL_db = "sports";
		$this->mySQLdb = new Database($SQL_db);
		$this->mySQLdb->dbConnect();

		$this->campus = 1;

		$this->id = $snid;
		$this->exists = $this->seasonExists();

	}

	function getCurrent()
	{
		$query = "SELECT season, id, date_format(startDate, '%y') as syear, date_format(startDate, '%m') as smonth, date_format(startDate, '%d') as sday , date_format(endDate, '%y') as eyear, date_format(endDate, '%m') as emonth, date_format(endDate, '%d') as eday, startDate, endDate, seasonType as type FROM season WHERE current = 1 AND campus = '".$this->campus."'";
		$this->mySQLdb->dbDoQuery($query);
		if ($this->mySQLdb->dbCountRows() > 0){
			$season = $this->mySQLdb->dbGetNextArray();
			$season['season'] = stripslashes($season['season']);
			return $season;
		}
		else
			print "Must set current season";
	}

	function getSeasons()
	{
		$query = "SELECT current, season, id, date_format(startDate, '%Y') as syear, date_format(startDate, '%m') as smonth, date_format(startDate, '%d') as sday , date_format(endDate, '%Y') as eyear, date_format(endDate, '%m') as emonth, date_format(endDate, '%d') as eday, startDate, endDate, seasonType as type FROM season WHERE campus = '".$this->campus."'
		";
		$this->mySQLdb->dbDoQuery($query);

		while ($season = $this->mySQLdb->dbGetNextArray())
		{
			$season['season'] = stripslashes($season['season']);
			$listOfSeasons[] = $season;
		}
		return $listOfSeasons;
	}

	function addSeason($season, $startDate, $endDate, $curr, $type)
	{
		$season  = addslashes($season);
		if (!$this->exists)
		{
			$query = "SELECT * FROM season 
				  WHERE season = '".$season."' 
				  AND 	startDate = '".$startDate."' 
				  AND 	endDate = '".$endDate."' 
				  AND 	current = '".$curr."' 
				  AND 	seasonType = '".$type."'
				  AND   campus = '".$this->campus."'
			";
			$this->mySQLdb->dbDoQuery($query);
			if ($this->mySQLdb->dbCountRows() == 0)
			{
				$query = "INSERT INTO season 
					(season, startDate, endDate, current, campus, seasonType) 
					  VALUES 
					('".$season."', '".$startDate."', '".$endDate."', '".$curr."', '".$this->campus."', '".$type."') 
				";
				$this->mySQLdb->dbDoQuery($query);
				if ($curr == 1){
					$insertID = $this->mySQLdb->dbInsertId();
					$query = "UPDATE season 
						  SET current = 0 
						  WHERE id <> '".$insertID."'
						  AND campus = '".$this->campus."'
					";
					$this->mySQLdb->dbDoQuery($query);
				}
				return 1;
			}
			else
				error(38);
		}
		else
			error(38);
	}

	function modifySeason( $season, $startDate, $endDate, $curr, $type) 
  	{
		$season  = addslashes($season);
		if($this->exists)
		{
			if ($curr == 1){
				$query = "UPDATE season 
					  SET current = 0 
					  WHERE id <> '".$this->id."'
					  AND campus = '".$this->campus."'
				";
				$this->mySQLdb->dbDoQuery($query);
			}
			$query = "UPDATE season 
				  SET 	season = '".$season."', 
				  	startDate = '".$startDate."',
				  	endDate = '".$endDate."',
				  	current = '".$curr."', 
				  	seasonType = '".$type."'
				  WHERE id = '".$this->id."'
				  AND	campus = '".$this->campus."'
			";
			$this->mySQLdb->dbDoQuery($query);
		}
		else
		{
			print "season didn't exist";
		}
	}	
	
	function deleteSeason()
	{
		if($this->exists)
		{
			$query = "SELECT * FROM season WHERE id = '".$this->id."'";
			$this->mySQLdb->dbDoQuery($query);
			$season = $this->mySQLdb->dbGetNextArray();
			$query = "SELECT e.eid FROM event e
				  LEFT JOIN team t ON t.tid = e.tid
				  LEFT JOIN sport s ON s.sid = t.sid 
					AND s.campus = '".$season['campus']."' AND s.seasonType = '".$season['seasonType']."'
				  WHERE datetime BETWEEN '".$season['startDate']."' AND '".$season['endDate']."'
			"; 
			$this->mySQLdb->dbDoQuery($query);
			while ($event = $this->mySQLdb->dbGetNextArray()){
				$query = "DELETE FROM event
				  	  WHERE eid = '".$event['eid']."'
				";
			}
			$query = "DELETE FROM season 
				  WHERE id = '".$this->id."' 
			";	
			$this->mySQLdb->dbDoQuery($query);
		}
	}
		
	function seasonExists()
	{
		$query = "SELECT * FROM season WHERE id = '".$this->id."'";
		$this->mySQLdb->dbDoQuery($query);

		if ($this->mySQLdb->dbCountRows() > 0)
			return 1;
		else
			return 0;
	}
}
	

?>
