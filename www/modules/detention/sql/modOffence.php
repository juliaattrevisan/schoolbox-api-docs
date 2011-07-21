<?php

//offence object...allows you to add/modify/delete offence table in database

class offence
{
	function offence($id = "")
	{
		
		//setup database
		$this->mySQLdb = new DeprecatedDatabase("detention");
		$this->mySQLdb->dbConnect();

		$this->id = $id;
		if ($this->id){
			$this->exists = $this->offenceExists();
		}
	}
	
	function getOffence()
	{
		$query = "SELECT id, description
			  FROM offence
			  WHERE `deleted` = 0
			  ORDER BY description
		";
	 	$this->mySQLdb->query($query);	   	
		while ($offence = $this->mySQLdb->getNextArray())
		{
			$offence['description'] = stripslashes($offence['description']);
			$listOfOffences[] = $offence;
		}
		return $listOfOffences;
	}

	function modOffence($desc)
	{
		if($this->exists)
		{
			$query = "UPDATE offence
				  SET description = '$desc'
				  WHERE id = '".$this->id."'
				"; 
			if($this->mySQLdb->query($query))
				return 1;
			else
				error(21);
		}
		else
			error(38);
	}

	function addOffence($description)
	{
		$description = addslashes($description);
		if(!$this->exists)
		{

			// Mark Parncutt 19 Jan 2005
			// Avoid having more than one offence with the same name
			$query = "SELECT * FROM offence
					  WHERE `description` = '$description'
					  AND `deleted` = 0";
			$result = $this->mySQLdb->query($query);

			if ($this->mySQLdb->countRows($result))
				error(56);

			$query = "INSERT INTO offence (description) 
				  VALUES ('".$description."')
			";
			if($this->mySQLdb->query($query))
				return 1;
			else
				error(21);
		}
		else
			error(38);
		
	}

	function offused($id)
	{
		$query = "SELECT *
			  FROM 	 det
			  WHERE	 offence = '$id'
		";
	 	$this->mySQLdb->query($query);
		if ($this->mySQLdb->countRows() > 0)
			return 1;
		else
			return 0;
	}
	
	function deleteOffence()
	{
		if($this->exists)
		{
			// Mark Parncutt 18 Jan 2005
			// Mark the offence as deleted rather than deleting it from the database
			// this will prevent problems with detentions already using this offence
			$query = "UPDATE `offence` SET `deleted` = 1 WHERE `id` = '".$this->id."'";
			if($this->mySQLdb->query($query))
				return 1;
			else
				error(21);
		}
//		else 
//			"whoops, not existing...";

	}

	function offenceExists($id = "")
	{
		if ($id)
		{
			$this->id = $id;
		}
		$query = "SELECT * FROM offence 
			  WHERE  id = '".$this->id."'
		";
		$this->mySQLdb->query($query);
		if ($this->mySQLdb->countRows() > 0)
			return 1;
		else
			return 0;
	}
}

?>
turn 0;
	}
}

?>
