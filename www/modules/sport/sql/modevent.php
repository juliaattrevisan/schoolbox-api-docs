<?php

//event object...allows you to add/modify/delete events from event table

class event
{
	function event($eid = "") 
	{
		//setup database
		$SQL_db = "sports";
		$this->mySQLdb = new dbManager($SQL_db);
		$this->mySQLdb->dbConnect();

		$this->eid = $eid;
		$this->exists = $this->eventExists();
	}

	function getEventDetails($sid, $type, $date = "")
	{
		//events for whole season
		if ($type == 1){
			$seasonobj = new season();
			$seasonDetails = $seasonobj->getCurrent();
			$whereParms = "AND e.datetime BETWEEN '".$seasonDetails['startDate']."' AND '".$seasonDetails['endDate']."'";
		}
		//events for current week
		else if ($type == 0) {
			$whereParms = "AND to_days(e.datetime) - to_days(now()) <= 7 AND to_days(e.datetime) - to_days(now()) >= 0"; 
		}
		//events for previous events 
		else if ($type == 2){
			$whereParms = "AND to_days(e.datetime) - to_days(now()) < 0";
		}
		//events for particular date
		else if ($type == 3){
			$whereParms = "AND to_days(e.datetime) = to_days('$date')";
		}
		$query = "SELECT e.eid, e.tid, e.vid, e.oid, e.win, e.result,
				 t.section, t.sid , t.teamorder,
				 v.venue, v.melref, 
				 o.opp,
				 st1.pos as pos1, st1.staffid as staffid1, 
			  	 s1.lastname as lname1, s1.firstname as fname1, s1.phone as phone1, s1.email as email1, 
				 st2.pos as pos2, st2.staffid as staffid2, 
				 s2.lastname as lname2, s2.firstname as fname2, s2.phone as phone2, s2.email as email2, 
				 date_format(e.datetime, '%l:%i %p') as time, 
				 date_format(e.datetime, '%p') as period,
				 date_format(e.datetime, '%l') as hour, 
				 date_format(e.datetime, '%i') as min, 
				 date_format(e.datetime, '%m') as month, 
				 date_format(e.datetime, '%d') as day,
				 date_format(e.datetime, '%Y') as year,
				 date_format(e.datetime, '%Y-%m-%d') as date
			  FROM 	event e
			  LEFT JOIN team t ON e.tid = t.tid
			  LEFT JOIN venue v ON e.vid = v.vid
			  LEFT JOIN opp o ON e.oid = o.oid 
			  LEFT JOIN staffteam st1 ON e.tid = st1.tid AND st1.pos = 1 
			  LEFT JOIN staff s1 ON st1.staffid = s1.staffid
			  LEFT JOIN staffteam st2 ON e.tid = st2.tid AND st2.staffid <> st1.staffid AND st2.pos = 2 
			  LEFT JOIN staff s2 ON st2.staffid = s2.staffid 
			  WHERE t.sid = '".$sid."'
			  ".$whereParms."
			  ORDER BY  date, t.teamorder
		";      
		//execute query
		$this->mySQLdb->dbDoQuery($query);
		while ($event = $this->mySQLdb->dbGetNextArray())
		{
			$event['result'] = stripslashes($event['result']);
			$event['section'] = stripslashes($event['section']);
			$event['melref'] = stripslashes($event['melref']);
			$event['lname1'] = stripslashes($event['lname1']);
			$event['lname2'] = stripslashes($event['lname2']);
			$event['fname1'] = stripslashes($event['fname1']);
			$event['fname2'] = stripslashes($event['fname2']);
			$event['phone1'] = stripslashes($event['phone1']);
			$event['phone2'] = stripslashes($event['phone2']);
			$event['email1'] = stripslashes($event['email1']);
			$event['email2'] = stripslashes($event['email2']);
			$listOfEvents[] = $event;
		}
		return $listOfEvents;
	}

	function addEvent($tid, $vid, $datetime, $oid)
	{
		if (!$this->exists)
		{
			$query = "SELECT * from event where tid = '".$tid."' and datetime = '".$datetime."'";
			$this->mySQLdb->dbDoQuery($query);
			if ($this->mySQLdb->dbCountRows() == 0)
			{
				$query = "INSERT INTO event (tid, vid, datetime, oid) VALUES ('".$tid."','".$vid."','".$datetime."','".$oid."')";
				if ($this->mySQLdb->dbDoQuery($query))
					return 1;
				else
					error(38);
				
			}
			else
			{
				error(38);
			}
				
		}
		else
			error(38);
	}

	function modifyEvent($tid, $vid, $datetime, $oid, $win, $result, $admin) 
  	{
		if($this->exists)
		{
			$result = addslashes($result);
			$query = "UPDATE event SET ";
			//$admin = 1: event.php $admin= 0: results.php
			if ($admin){
				$query.=" tid = '".$tid."', 
					  vid = '".$vid."',
					  datetime = '".$datetime."',
					  oid = '".$oid."'
				";
			}
			else {  
				$query.=" win = '".$win."',
					  result = '".$result."'
				";
			}
			$query.=" WHERE eid = '".$this->eid."'";
			$this->mySQLdb->dbDoQuery($query);
		}
		else
		{
			print "event didn't exist";
		}
	}	
	
	function deleteEvent()
	{
		if ($this->exists)
		{
			$query = "DELETE FROM event 
				  WHERE eid = '".$this->eid."' 
				";	
			$this->mySQLdb->dbDoQuery($query);
		}
	}
		
	function eventExists()
	{
		$query = "SELECT * FROM event WHERE eid = '".$this->eid."'";
		$this->mySQLdb->dbDoQuery($query);

		if ($this->mySQLdb->dbCountRows() > 0)
			return 1;
		else
			return 0;
	}
}
	

?>
