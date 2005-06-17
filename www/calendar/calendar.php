<?php

/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

/* This class provides the database abstraction for the calender system */
/* It provides security, creation, and viewing of events		*/


class calendar
{

function calendar()
{
	$this->db = new dbManager;
}

function displayColour($id)
{
	if($id==4) //black
		return "#000000";
	else if($id==3) //blue
		return "#0033FF";   
	else if($id==2) //green
		return "#009933";
	else if($id==1) //red
		return "#FF0000";
	else
		return "#000000";
}

function checkSecurity($level, $id)
{
	//check use has rights to post a message with level above personal
	//returns 1 on read, 2 on write (can modify own) and 3 on moderate	

	if($level == 1) //personal
	{
		return 2;
	}
	else if($level == 2) //group
	{
		return perm($id, 1);
	}
	else if($level == 3) //campus
	{
		return perm($id, 2);
	}
	else if($level == 4) //all
	{
		return perm(0, 2);
	}
	else
		return 0;
}

function getAllDayEvents($currDay, $currMonth, $currYear)
{
	//gets normal events and group shared events and merges them into an one array
	$normalevents = $this->getDayEvents($currDay, $currMonth, $currYear);
	$groupevents =  $this->getAllGroupDayEvents($currDay, $currMonth, $currYear);

	$event = array_merge_recursive($normalevents,$groupevents);
	
	if(is_array($event['note']))
	{
		//sort array on start time
		array_multisort($event['startTime'], SORT_ASC, SORT_NUMERIC, $event['id'],$event['owner'], 
			$event['endTime'], $event['priority'], $event['note'], $event['type']);
	}

	return $event;
}


function checkOwnership($id, $type)
{
	//returns owner id of post
	//type must be specified
	// 1 = single, 2 = weekly, 3 = monthly

	if($type == 1)
	{
		$query = "SELECT owner FROM event WHERE id = '$id'";
	}
	if($type == 2)
	{
		$query = "SELECT owner FROM eventWeekly WHERE id = '$id'";
	}
	if($type == 3)
	{
		$query = "SELECT owner FROM eventMonthly WHERE id = '$id'";
	}

	$result = $this->db->dbGetSingleRow($query);

	return $result['owner'];
}

function addSingleEvent($date, $startTime, $endTime, $priority, $note, $level = 1, $sid = 0)
{
	$query = "INSERT INTO event (owner, date, startTime, endTime, priority, note, access, sid) 
	VALUES ('".$_SESSION['id']."', FROM_UNIXTIME('$date'), '$startTime', '$endTime', '$priority', 
	'$note', '$level', '$sid')";
	
	if($this->checkSecurity($level, $sid) > 1) //check has permission to write
	{
		$this->db->dbDoQuery($query);
	}
	else
		error(35); //do not have permission to add event
}

function modSingleEvent($id, $date, $startTime,  $endTime, $priority, $note, $level = 1, $sid = 0)
{
	$query = "UPDATE event SET date=FROM_UNIXTIME('$date'), startTime='$startTime', 
		endTime='$endTime', priority='$priority', note='$note', access='$level', sid='$sid' 
		WHERE id='$id'";
	
	if(($this->checkSecurity($level, $sid) > 2) || ($this->checkOwnership($id, 1) == $_SESSION['id']))
	{
		$this->db->dbDoQuery($query);
	}
	else
		error(34); //do not have permission to modify event
}

function deleteSingleEvent($id, $level = 1, $sid = 0)
{
	$query = "DELETE FROM event WHERE id = '$id'"; 

	if(($this->checkSecurity($level, $sid) > 2) || ($this->checkOwnership($id, 1) == $_SESSION['id']))
	{
		$this->db->dbDoQuery($query);
	}
	else
		error(34); //do not have permission to modify event

}



function addWeeklyEvent($day, $startTime, $endTime, $startDuration, $endDuration, $priority, $note, $level = 1, $sid = 0)
{
	$query = "INSERT INTO eventWeekly 
			(owner, day, startTime, endTime, startDuration, endDuration, priority, note, access, sid) 
		VALUES 
			('".$_SESSION['id']."', '$day', '$startTime', '$endTime', FROM_UNIXTIME('$startDuration'), 
			FROM_UNIXTIME('$endDuration'), '$priority', '$note', '$level', '$sid')";

	if($this->checkSecurity($level, $sid) > 1) //check has permission to write
	{
		$this->db->dbDoQuery($query);
	}
	else
		error(35); //do not have permission to add event

}

function modWeeklyEvent($id, $day, $startTime,  $endTime, $startDuration, $endDuration, $priority, $note, $level = 1, $sid = 0)
{
	$query = "UPDATE eventWeekly SET day='$day', startTime='$startTime', 
		endTime='$endTime', startDuration=FROM_UNIXTIME('$startDuration'), endDuration=FROM_UNIXTIME('$endDuration'),
 		priority='$priority', note='$note', access='$level', sid='$sid' 
		WHERE id='$id'";
	
	//has moderator access or is owner
	if(($this->checkSecurity($level, $sid) > 2) || ($this->checkOwnership($id, 2) == $_SESSION['id']))
	{
		$this->db->dbDoQuery($query);
	}
	else
		error(34); //do not have permission to modify event
}

function deleteWeeklyEvent($id, $level = 1, $sid = 0)
{
	$query = "DELETE FROM eventWeekly WHERE id = '$id'"; 

	if(($this->checkSecurity($level, $sid) > 2) || ($this->checkOwnership($id, 2) == $_SESSION['id']))
	{
		$this->db->dbDoQuery($query);
	}
	else
		error(34); //do not have permission to modify event

}

function addMonthlyDayEvent($day, $dayOcc, $startTime, $endTime, $startDuration, $endDuration, $priority, $note, $level = 1, $sid = 0)
{

	$query = "INSERT INTO eventMonthly 
			(owner, day, dayOcc, startTime, endTime, startDuration, endDuration, priority, note, access, sid) 
		VALUES 
			('".$_SESSION['id']."', '$day', '$dayOcc', '$startTime', '$endTime', FROM_UNIXTIME('$startDuration'), 
			FROM_UNIXTIME('$endDuration'), '$priority', '$note', '$level', '$sid')";

	if($this->checkSecurity($level, $sid) > 1) //check has permission to write
	{
		$this->db->dbDoQuery($query);
	}
	else
		error(35); //do not have permission to add event

}

function modMonthlyDayEvent($id, $day, $dayOcc, $startTime,  $endTime, $startDuration, $endDuration, $priority, $note, $level = 1, $sid = 0)
{
	$query = "UPDATE eventMonthly SET day='$day', dayOcc='$dayOcc', startTime='$startTime', 
		endTime='$endTime', startDuration=FROM_UNIXTIME('$startDuration'), endDuration=FROM_UNIXTIME('$endDuration'),
 		priority='$priority', note='$note', access='$level', sid='$sid' 
		WHERE id='$id'";

	//has moderator access or is owner
	if(($this->checkSecurity($level, $sid) > 2) || ($this->checkOwnership($id, 3) == $_SESSION['id']))
	{
		$this->db->dbDoQuery($query);
	}
	else
		error(34); //do not have permission to modify event
}

function addMonthlyDateEvent($date, $startTime, $endTime, $startDuration, $endDuration, $priority, $note, $level = 1, $sid = 0)
{
	$query = "INSERT INTO eventMonthly 
			(owner, date, startTime, endTime, startDuration, endDuration, priority, note, access, sid) 
		VALUES 
			('".$_SESSION['id']."', '$date', '$startTime', '$endTime', FROM_UNIXTIME('$startDuration'), 
			FROM_UNIXTIME('$endDuration'), '$priority', '$note', '$level', '$sid')";

	if($this->checkSecurity($level, $sid) > 1) //check has permission to write
	{
		$this->db->dbDoQuery($query);
	}
	else
		error(35); //do not have permission to add event
}

function modMonthlyDateEvent($id, $date, $startTime,  $endTime, $startDuration, $endDuration, $priority, $note, $level = 1, $sid = 0)
{
	$query = "UPDATE eventMonthly SET date='$date', startTime='$startTime', 
		endTime='$endTime', startDuration=FROM_UNIXTIME('$startDuration'), endDuration=FROM_UNIXTIME('$endDuration'),
 		priority='$priority', note='$note', access='$level', sid='$sid' 
		WHERE id='$id'";

	//has moderator access or is owner
	if(($this->checkSecurity($level, $sid) > 2) || ($this->checkOwnership($id, 3) == $_SESSION['id']))
	{
		$this->db->dbDoQuery($query);
	}
	else
		error(34); //do not have permission to modify event
}

function deleteMonthlyEvent($id, $level = 1, $sid = 0)
{
	$query = "DELETE FROM eventMonthly WHERE id = '$id'"; 

	if(($this->checkSecurity($level, $sid) > 2) || ($this->checkOwnership($id, 2) == $_SESSION['id']))
	{
		$this->db->dbDoQuery($query);
	}
	else
		error(34); //do not have permission to modify event

}



function getSingleEvent($id, $type)
{
	//get single event to populate modify form

	if($type == "week")
		$query = "SELECT * FROM eventWeekly WHERE id = '$id'";
	else if($type == "month")
		$query = "SELECT * FROM eventMonthly WHERE id = '$id'";
	else
		$query = "SELECT * FROM event WHERE id = '$id'";

	$event = $this->db->dbGetSingleRow($query);
	return $event;
}

function getDayEvents($day, $month, $year)
{
	//this function will return all events occuring on the date specified
		
	//todays date stamp
	$datestamp = mktime(0,0,0,$month,$day,$year);
	$dayno = date("w", $datestamp)+1;
	$dayocc = ceil($date/7);  
	$eventCount = 0;
	$campuslist = implode(',',$_SESSION['campus']);

	//get single events for the day
	$querysingle = "SELECT id, owner,TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, 
	TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, 
	priority, note FROM event WHERE date = FROM_UNIXTIME('$datestamp')
	AND ( (access = '4') OR (access = '1' AND owner= '".$_SESSION['id']."') 
		OR (access = '3' AND sid IN($campuslist) ))
	ORDER BY startTime, endTime";

	//get weekly events that occur today
	$queryweekly = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, 
	TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note FROM eventWeekly WHERE day = '$dayno' 
	AND startDuration < FROM_UNIXTIME('$datestamp') AND endDuration > FROM_UNIXTIME('$datestamp')
	AND ( (access = '4') OR (access = '1' AND owner= '".$_SESSION['id']."') 
		OR (access = '3' AND sid IN($campuslist)))
	ORDER BY startTime, endTime";

	//get monthly events that occur today
	$querymonthly = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime,
	TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note FROM eventMonthly WHERE
	((day = '$dayno' AND dayocc='$dayocc') OR (date='$day'))  
	AND startDuration < FROM_UNIXTIME('$datestamp') AND endDuration > FROM_UNIXTIME('$datestamp')
	AND ( (access = '4') OR (access = '1' AND owner= '".$_SESSION['id']."') 
		OR (access = '3' AND sid IN($campuslist)))
	ORDER BY startTime, endTime";

	return $this->getCalendarArray($querysingle, $queryweekly, $querymonthly);
}

function getGroupDayEvents($sid, $day, $month, $year)
{
	//this function will return all events occuring in the group on the date specified
	
	//todays date stamp
	$datestamp = mktime(0,0,0,$month,$day,$year);
	$dayno = date("w", $datestamp)+1;
	$dayocc = ceil($date/7);  

	//get single events for the day
	$querysingle = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note 
	FROM event, userAccess WHERE 
	date = FROM_UNIXTIME('$datestamp') AND access = '2' AND uid = ".$_SESSION['id']." 
	AND service = '1' AND sid = '$sid' AND userAccess.sid = event.sid
	ORDER BY startTime, endTime";

	//get weekly events that occur today
	$queryweekly = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note 
	FROM eventWeekly, userAccess WHERE day = '$dayno' 
	AND startDuration < FROM_UNIXTIME('$datestamp') AND endDuration > FROM_UNIXTIME('$datestamp')
	AND uid = ".$_SESSION['id']." AND access = '2' AND service = '1' AND sid = '$sid' AND userAccess.sid = event.sid
	ORDER BY startTime, endTime";

	//get monthly events that occur today
	$querymonthly = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note 
	FROM eventMonthly WHERE
	((day = '$dayno' AND dayocc='$dayocc') OR (date='$day'))  
	AND startDuration < FROM_UNIXTIME('$datestamp') AND endDuration > FROM_UNIXTIME('$datestamp')
	AND uid = ".$_SESSION['id']." AND access = '2' AND service = '1' AND sid = '$sid' AND userAccess.sid = event.sid
	ORDER BY startTime, endTime";


	return $this->getCalendarArray($querysingle, $queryweekly, $querymonthly);
	
}

function getAllGroupDayEvents($day, $month, $year)
{
	//this function will return all events occuring in the users groups on the date specified
	
	//todays date stamp
	$datestamp = mktime(0,0,0,$month,$day,$year);
	$dayno = date("w", $datestamp)+1;
	$dayocc = ceil($date/7);  

	//get single events for the day
	$querysingle = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note 
	FROM event, userAccess WHERE 
	date = FROM_UNIXTIME('$datestamp') AND uid = ".$_SESSION['id']." AND access = '2' 
	AND service = '1' AND userAccess.sid = event.sid
	ORDER BY startTime, endTime";

	//get weekly events that occur today
	$queryweekly = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note 
	FROM eventWeekly, userAccess WHERE day = '$dayno' 
	AND startDuration < FROM_UNIXTIME('$datestamp') AND endDuration > FROM_UNIXTIME('$datestamp')
	AND uid = ".$_SESSION['id']." AND access = '2' AND service = '1' AND userAccess.sid = eventWeekly.sid
	ORDER BY startTime, endTime";

	//get monthly events that occur today
	$querymonthly = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note 
	FROM eventMonthly, userAccess WHERE
	((day = '$dayno' AND dayocc='$dayocc') OR (date='$day'))  
	AND startDuration < FROM_UNIXTIME('$datestamp') AND endDuration > FROM_UNIXTIME('$datestamp')
	AND uid = ".$_SESSION['id']." AND access = '2' AND service = '1' AND userAccess.sid = eventMonthly.sid
	ORDER BY startTime, endTime";


	return $this->getCalendarArray($querysingle, $queryweekly, $querymonthly);
	
}

function getSubmissionEvents($day, $month, $year)
{

	//get a list of all the possible submission box categories
	require_once($_SERVER['DOCUMENT_ROOT']."/include/involved.php");
	$catList = involvedCategoryList(1);

	if(is_array($catList['id']))
	{
		$date = mktime(0,0,0,$month,$day,$year);
		$i=0;	

		foreach($catList['id'] as $catID)
		{
			$query = "SELECT category.name, file.description FROM category, file, submission_box WHERE category.id = 
				submission_box.category AND file.id = submission_box.fid AND submission_box.category = '$catID' AND 
				submission_box.date = FROM_UNIXTIME('$date')";
	
			$this->db->dbDoQuery($query);

			if($this->db->dbCountRows() > 0)
			{
				$subEvent = $this->db->dbGetNextArray(); 	
	
				$subEvents['category'][$i] = $subEvent['name'];
				$subEvents['description'][$i] = $subEvent['description'];
	
				$i++;
			}
		}
	}

	return $subEvents;
}


function getCalendarArray($query1, $query2, $query3)
{

	$eventCount = 0;
	$this->db->dbDoQuery($query1);
	while( $line = $this->db->dbGetNextArray() )
	{
		$dayEvents['id'][$eventCount] = $line['id'];		
		$dayEvents['owner'][$eventCount] = $line['owner'];		
		$dayEvents['startTime'][$eventCount] = $line['startTime'];		
		$dayEvents['endTime'][$eventCount] = $line['endTime'];		
		$dayEvents['priority'][$eventCount] = $line['priority'];		
		$dayEvents['note'][$eventCount] = $line['note'];		
		$dayEvents['type'][$eventCount] = "day";
		$eventCount++;
	}

	$this->db->dbDoQuery($query2);
	while( $line = $this->db->dbGetNextArray() )
	{
		$dayEvents['id'][$eventCount] = $line['id'];		
		$dayEvents['owner'][$eventCount] = $line['owner'];		
		$dayEvents['startTime'][$eventCount] = $line['startTime'];		
		$dayEvents['endTime'][$eventCount] = $line['endTime'];		
		$dayEvents['priority'][$eventCount] = $line['priority'];		
		$dayEvents['note'][$eventCount] = $line['note'];		
		$dayEvents['type'][$eventCount] = "week";
		$eventCount++;
	}

	$this->db->dbDoQuery($query3);
	while( $line = $this->db->dbGetNextArray() )
	{
		$dayEvents['id'][$eventCount] = $line['id'];		
		$dayEvents['owner'][$eventCount] = $line['owner'];		
		$dayEvents['startTime'][$eventCount] = $line['startTime'];		
		$dayEvents['endTime'][$eventCount] = $line['endTime'];		
		$dayEvents['priority'][$eventCount] = $line['priority'];		
		$dayEvents['note'][$eventCount] = $line['note'];		
		$dayEvents['type'][$eventCount] = "month";
		$eventCount++;
	}

	return $dayEvents;
}

} ?>
