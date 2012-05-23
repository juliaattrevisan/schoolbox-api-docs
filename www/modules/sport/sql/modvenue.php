<?php

//venue object...allows you to add/modify/delete venues from venue table

class venue
{
	function venue($id = "")
	{
		
		//setup database
		$SQL_db = "sports";
		$this->mySQLdb = new DeprecatedDatabase($SQL_db);
		$this->mySQLdb->dbConnect();

		$this->campus = 1;

		$this->id = $id;
		if ($this->id)
		{
			$this->exists = $this->venueExists();
		}
	}

	function getVenues()
	{
		$query = "SELECT vid, venue, melref, lat, long
			  FROM venue
			  ORDER BY venue
		";		
		$this->mySQLdb->dbDoQuery($query);
		while ($venue = $this->mySQLdb->dbGetNextArray())
		{
			$venue['venue'] = stripslashes($venue['venue']);
			$venue['melref'] = stripslashes($venue['melref']);
			$listOfVenues[] = $venue; 
		}
		return $listOfVenues;
	}
	
	function addVenue($venue, $melref, $lat, $long)
	{
		$venue = addslashes($venue);
		$melref = addslashes($melref);
		if(!$this->exists)
		{
			$query = "SELECT * FROM venue WHERE venue = '".$venue."' AND melref = '".$melref."'";
			$this->mySQLdb->dbDoQuery($query);
			if ($this->mySQLdb->dbCountRows() == 0)
			{
				$query = "INSERT INTO venue (venue, melref,campus,lat, long)
					  VALUES ('".$venue."','".$melref."','".$this->campus."', '$lat', '$long')
				";
				if($this->mySQLdb->dbDoQuery($query))
					return 1;
				else
					error(21);
			}
			else
				error(38);
		}
		else
			error(38);
	}
	
	function modifyVenues($venid, $venue, $melref, $lat, $long )
  	{
		$venue = addslashes($venue);
		$melref = addslashes($melref);
		if($this->exists)
		{
			$query = "UPDATE venue SET venue = '".$venue."', 
				  		   melref = '".$melref."'
				  		   lat = '".$lat."'
				  		   long = '".$long."'
				  WHERE vid = '".$venid."'
			";
			$this->mySQLdb->dbDoQuery($query);
			if($this->mySQLdb->dbDoQuery($query))
				return 1;
			else
				error(21);
		}
		else 
			print "didn't exist";
	}	
	
	function deleteVenue()
	{
		if($this->exists)
		{
			$query = "DELETE FROM venue 
				  WHERE vid = '".$this->id."'
			"; 
			if($this->mySQLdb->dbDoQuery($query))
				return 1;
			else
				error(21);
		}
	}
	
	function venueExists($id = "")
	{
		if ($id)
		{
			$this->id = $id;
		}
		$query = "SELECT * FROM venue 
			  WHERE vid = '".$this->id."'  
		";

		$this->mySQLdb->dbDoQuery($query);
		if ($this->mySQLdb->dbCountRows() > 0)
			return 1;
		else
			return 0;
	}
}
?>
