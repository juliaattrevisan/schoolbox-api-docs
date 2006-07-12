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
	 	$this->mySQLdb->dbDoQuery($query);	   	
		while ($status = $this->mySQLdb->dbGetNextArray())
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
			if($this->mySQLdb->dbDoQuery($query))
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
			$this->mySQLdb->dbDoQuery($query);
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
		$this->mySQLdb->dbDoQuery($query);
		if ($this->mySQLdb->dbCountRows() > 0)
			return 1;
		else
			return 0;
	}
}

?>
