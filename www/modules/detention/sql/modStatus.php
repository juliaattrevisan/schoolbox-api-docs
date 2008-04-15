<?php

//status object...allows you to add/modify/delete status table in database

class status
{
	function status($id = "")
	{
		
		//setup database
		$this->mySQLdb = new dbManager("detention");
		$this->mySQLdb->dbConnect();

		$this->id = $id;
		if ($this->id){
			$this->exists = $this->statusExists();
		}
	}
	
	function getStatusList()
	{
		$query = "SELECT id, type
			  FROM status
			  ORDER BY type
		";
	 	$this->mySQLdb->query($query);	   	
		while ($status = $this->mySQLdb->getNextArray())
		{
			$status['type'] = stripslashes($status['type']);
			$listOfStatuss[] = $status;
		}
		return $listOfStatuss;
	}

	function addStatus($type)
	{
		$cwtype = addslashes($description);
		if(!$this->exists)
		{
			$query = "INSERT INTO status (type) 
				  VALUES ('".$type."')
			";
			if($this->mySQLdb->query($query))
				return 1;
			else
				error(21);
		}
		else
			error(38);
		
	}
	
	function deleteStatus()
	{
		if($this->exists)
		{
			$query = "DELETE FROM status WHERE id = '".$this->id."'";
			$this->mySQLdb->query($query);
		}
		else 
			"whoops, not existing...";

	}

	function statusExists($id = "")
	{
		if ($id)
		{
			$this->id = $id;
		}
		$query = "SELECT * FROM status 
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
