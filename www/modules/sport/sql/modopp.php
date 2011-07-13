<?php
//opp object...allows you to add/modify/delete opps from opp table

class opp
{
	function opp($id = "")
	{
	 	
		//setup database
		$SQL_db = "sports";
		$this->mySQLdb = new Database($SQL_db);
		$this->mySQLdb->dbConnect();

		$this->campus = 1;

		$this->id = $id;
		if ($this->id)
		{
			$this->exists = $this->oppExists($this->id);
		}
	}

	function getOpp()
	{
		$query = "SELECT oid, opp
			  FROM opp
			  ORDER BY opp
		";		
		$this->mySQLdb->dbDoQuery($query);
		while ($opp = $this->mySQLdb->dbGetNextArray())
		{
                        $opp['opp'] = stripslashes($opp['opp']);
			$listOfOpps[] = $opp;
		}
		return $listOfOpps;
	}

	function getAnOpp($oid)
	{
		$query = "SELECT opp FROM opp WHERE oid = '".$oid."'"; 
		$this->mySQLdb->dbDoQuery($query);
		if ($opp = $this->mySQLdb->dbGetNextArray())
		{
                        $opp['opp'] = stripslashes($opp['opp']);
			return $opp;
		}
		else
			return 0; 
	}

	function addOpp($opp)
	{
		$opp = addslashes($opp);
		if(!$this->exists)
		{
			$query = "SELECT * FROM opp 
				  WHERE opp = '".$opp."' 
			";
			$this->mySQLdb->dbDoQuery($query);
			if ($this->mySQLdb->dbCountRows() == 0)
			{	
				$query = "INSERT INTO opp (opp, campus) 
					  VALUES ('".$opp."', '".$this->campus."')
				";
				if ($this->mySQLdb->dbDoQuery($query))
					return 1;
				else
					return 0;
			}
			else
				error(38);
		}
		else
			error(38);
	}
	
	function modifyOpps($opp)
  	{
		$opp = addslashes($opp);
		if($this->exists)
		{
			$query = "UPDATE opp SET opp = '".$opp."'
				  WHERE oid = '".$this->id."'
			";
			$this->mySQLdb->dbDoQuery($query);
			$this->mySQLdb->dbDoQuery($query);
	}
		else 
			print "didn't exist";
	}	
	
	function deleteOpp()
	{
		if($this->exists)
		{
			$query = "DELETE FROM opp 
				  WHERE oid = '".$this->id."'
			"; 
			$this->mySQLdb->dbDoQuery($query);
		}
	}
	
	function oppExists($id = "")
	{
		if ($id)
		{
			$this->id = $id;
		}
		$query = "SELECT * FROM opp 
			  WHERE oid = '".$this->id."'  
		";
		$this->mySQLdb->dbDoQuery($query);
		if ($this->mySQLdb->dbCountRows() > 0)
			return 1;
		else	
			return 0;
		
	}
}
?>
