<?php

//date object...allows you to add/modify/delete the date table in database
require_once("notify.php");
require_once("modDet.php");

class date
{
	function date($id = "")
	{
		
		//setup database
		$SQL_db = "detention";
		$this->mySQLdb = new Database($SQL_db);
		$this->mySQLdb->dbConnect();

		$this->id = $id;
		if ($this->id){
			$this->exists = $this->dateExists($id);
		}
	}
	
	function findStaff($input)
	{
		$inputarr = explode(" ", $input);
		foreach ($inputarr as $name){
			$where.= " firstname like '%$name%' OR lastname like '%$name%' OR"; 
		}
	  	$where = substr($where, 0, strlen($where)-2);		
		$query = "SELECT 	id, firstname, lastname
			  FROM 		intranet.user
			  WHERE		group_id IN (1, 2) 
			  AND		(".$where.")
			  ORDER BY lastname, firstname
			";
		$this->mySQLdb->query($query);
	 	while ($stafflist = $this->mySQLdb->getNextArray()){
			$id = $stafflist['id']; 
			$listOfStaff[$id] = stripslashes($stafflist['lastname']).", ".stripslashes($stafflist['firstname']);
		}			
		return $listOfStaff;
	}
	
	function addStaff($id)
	{
		$query = "INSERT INTO	intranet.userAccess
			  		(uid, service, sid, type)
			  VALUES	($id, 5, 0, 2)
			";
		$this->mySQLdb->query($query);
	}

	function getStaff()
	{
		$query = "SELECT 	u.id, u.firstname, u.lastname
			  FROM 		intranet.userAccess ua
			  LEFT JOIN 	intranet.user u
			  ON		u.id = ua.uid
			  WHERE		ua.service = 5 
			  AND		ua.type >= 2
			  ORDER BY	u.lastname, u.firstname
			";
		$this->mySQLdb->query($query);
		while ($stafflist = $this->mySQLdb->getNextArray()){
			$stafflist['firstname'] = stripslashes($stafflist['firstname']);
			$stafflist['lastname'] = stripslashes($stafflist['lastname']);
			$id = $stafflist['id'];
			$listOfStaff[$id] = $stafflist['lastname'].", ".$stafflist['firstname'];
		}
		return $listOfStaff;	
	}

	function addLocation($loc)
	{
		// Mark Parncutt 19 Jan 2005
		// Avoid having multiple locations with the same name
		$query = "SELECT * FROM location
				  WHERE `location` = '$loc'
				  AND `deleted` = 0";
			$result = $this->mySQLdb->query($query);
			if ($this->mySQLdb->countRows($result))
				error(60);

		$query = "INSERT INTO	location
			  		(location)
			  VALUES	('$loc')
			";
		$this->mySQLdb->query($query);
	}

	function getLocations()
	{
		$query = "SELECT	id, location
			  FROM 		location
			  WHERE `deleted` = 0";
		$this->mySQLdb->query($query);
		while ($locs = $this->mySQLdb->getNextArray()){
			$id = $locs['id'];
			$listOfLocs[$id] = stripslashes($locs['location']);
		}
		return $listOfLocs;
	}
	
	function getDateList($where)
	{
		$query = "SELECT id, 
				date_format(detdatetime,'%l') as hour, 
				date_format(detdatetime, '%i') as minute, 
				date_format(detdatetime, '%p') as period, 
				date_format(detdatetime, '%b') as month, 
				date_format(detdatetime, '%e') as day, 
				type, 
				staff, 
				location
			  FROM 	detdate
		";
	 	$this->mySQLdb->query($query);	   	
		while ($schedule = $this->mySQLdb->getNextArray())
		{
			$listOfDates[] = $schedule;
		}
		return $listOfDates;
	}

	function getDateCurr($type = "")
	{
		$query = "SELECT	id, 
				 	date_format(detdatetime, '%a %D %b %l:%i%p') as detdate, 
				 	date_format(detdatetime, '%e') as day, 
				 	date_format(detdatetime, '%m') as mth, 
				 	date_format(detdatetime, '%l') as hr,
				 	date_format(detdatetime, '%i') as min, 
				 	date_format(detdatetime, '%p') as per,
					type,
					staff,
					location
			  FROM 		detdate  
			  WHERE		detdatetime > now()
			";
		if ($type != ""){
			$query.= " AND type = '$type'
				   ORDER BY type asc, detdatetime asc
			";
		}
	 	else
			$query.= " ORDER BY detdatetime asc, type asc";
		$this->mySQLdb->query($query);
		while ($datelist = $this->mySQLdb->getNextArray())
		{
			$listofDates[] = $datelist;
		}	
		return $listofDates;
	}

	function dateUsed($id)
	{
		// Robert Skillington Jan 2006
		// Changed query to make sure it wasn't selecting
		// cancelled detentions. 
		// Addition: ' AND status!=8'
		$query = "SELECT 	*
			  FROM		det
			  WHERE		detDate = '$id' AND status!=8";
		$this->mySQLdb->query($query);
		if ($this->mySQLdb->countRows() > 0)
			return 1;
		else
			return 0;
	}	

	function addDate($datetime, $type, $staff, $location)
	{
		$staff = addslashes($staff);
		$location = addslashes($location);
		$query = "SELECT * FROM detdate WHERE detdatetime = '$datetime' AND type = '$type' AND staff = '$staff' AND location = '$location'"; 
		$this->mySQLdb->query($query);
		if ($this->mySQLdb->countRows() == 0){
			$query = "INSERT INTO detdate (detdatetime, type, staff, location) 
				  VALUES ('".$datetime."', '".$type."', '".$staff."', '".$location."')
			";
			if($this->mySQLdb->query($query))
				return 1;
			else
				error(21);
		}
		else
			print "already in db";
	}

	function modDate($datetime, $type, $staff, $location)
	{
		if ($this->exists)
		{
			//determine if the details of this date have changed at all
			// notify any students with detentions on this date if so
		 	$query = "SELECT 	* 	
				  FROM 		detdate
				  WHERE		detdate.id = '".$this->id."'
				  AND		detdatetime = '$datetime'
				  AND		type = '$type'	
				  AND		staff = '$staff'
				  AND		location = '$location'
			";
		 	$this->mySQLdb->query($query);
			if ($this->mySQLdb->countRows() > 0){
				$diff = 0;
			}
			else {
				$diff = 1;
			}
			$staff = addslashes($staff);
			$location = addslashes($location);
			$query = "UPDATE detdate set detdatetime = '$datetime', type = '$type', staff = '$staff', location = '$location'
				  WHERE detdate.id = '".$this->id."' 
			";
			if($this->mySQLdb->query($query)){
				//if any details of detention have changed ie. diff = 1 then send notification
				if ($diff){
					//only get dets with status confirmed, notification for unconfirmed will occur upon confirmation
					$query = "SELECT det.id from det WHERE detDate = '".$this->id."' AND status IN (2, 3, 4)";
					$this->mySQLdb->query($query);
					
					//if no detentions using this date, don't try and send notification
					if ($this->mySQLdb->countRows() > 0){
					  while ($detlist = $this->mySQLdb->getNextArray()){
						$ids[] = $detlist['id'];
					  }
					  foreach ($ids as $id){
						//notify student of changes
						$myDet = new det($id);
						$detDetails = $myDet->getDet();
 						$studID = $detDetails[0]['studID'];
                                		$staffID = $detDetails[0]['staffID'];
                                		$off = $detDetails[0]['offence'];
                                		$workset = $detDetails[0]['workset'];
                                		$type = $detDetails[0]['type'];
                                		$date = $detDetails[0]['dateID'];
										$history = $detDetails[0]['history'];

                                		//get date into array for notify function to deal with
                                		$query = "SELECT date_format(d.detdatetime, '%a %D %b') as detdate, date_format(d.detdatetime, '%l:%i%p') as time, u.firstname, u.lastname, l.location
                                          		  FROM (detdate d, location l)
                                          		  LEFT JOIN intranet.user u ON u.id = d.staff
                                          		  WHERE d.id = '$date'
                                          		  AND d.location = l.id
                                		";
                                		$this->mySQLdb->query($query);
                                		$date = $this->mySQLdb->getNextArray();

                                		$notify = new notify("MOD", $date, $studID, $staffID, $off, $workset, $type, $history);
                                		$notify->mail();
					  }
					}
					else
						return 1;
				}
				return 1;
			}
			else {
				error(21);
			}
		}
		else
			print "couldn't modify";
	}
	
	function deleteDate()
	{
		if($this->exists)
		{
			$query = "DELETE FROM detdate WHERE id = '".$this->id."'";
			if ($this->mySQLdb->query($query))
				return 1;
			else
				error(21);
		}
		else 
			print "whoops, not existing...";
	}

	function dateExists($id = "")
	{
		if ($id)
		{
			$this->id = $id;
		}
		$query = "SELECT * FROM detdate 
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
