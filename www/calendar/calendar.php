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
	$this->db = new dbManager();
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

function getAllDayEvents($currDay, $currMonth, $currYear, $campus = 0)
{
	//gets normal events and group shared events and merges them into an one array
	$normalevents = $this->getDayEvents($currDay, $currMonth, $currYear, $campus);
	$groupevents =  $this->getAllGroupDayEvents($currDay, $currMonth, $currYear);

	if(is_array($normalevents) || is_array($groupevents))
		$event = array_merge($normalevents,$groupevents);
	else
		return false;
	
	//sort array on start time
	usort($event,array($this,"timecmp"));
	
	return $event;
}

function timecmp($a, $b)
{
	if($a['startTimestamp'] == 0)
		return 1;
	if($b['startTimestamp'] == 0)
		return -1;
		
	//just a quick function to enable sorting of calenar based on time
	if ($a['startTimestamp'] == $b['startTimestamp']) {
		return 0;
	}
	if($a['startTimestamp'] < $b['startTimestamp'])
   		return -1;
	else
		return 1;
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

function addPerms($id, $groupArray, $campusArray)
{
	$hasAuth = false;
	foreach($campusArray as $campus)
	{
		if($this->checkSecurity(3, $campus) > 1)
		{
			$this->insertCampus($id, $campus);
			$hasAuth = true;
		}
	}
	if($hasAuth)
	{
		foreach($groupArray as $group) 
		{
			$this->insertGroup($id, $group);
		}
	}
}

function delPerms($id)
{
	$query = "DELETE FROM eventGroup WHERE eventID = '$id'";
	$this->db->dbDoQuery($query);
	$query = "DELETE FROM eventCampus WHERE eventID = '$id'";
	$this->db->dbDoQuery($query);
}

function insertGroup($id, $group)
{
	$query = "INSERT INTO eventGroup VALUES ($id, $group)";
	return $this->db->dbDoQuery($query);
}

function insertCampus($id, $campus)
{
	$query = "INSERT INTO eventCampus VALUES ($id, $campus)";
	return $this->db->dbDoQuery($query);
}

function addSingleEvent($date, $startTime, $endTime, $priority, $note, $level = 1, $sid = 0, $userGroup = 0)
{
	if($level == 3)
	{
		$query = "INSERT INTO event (owner, date, startTime, endTime, priority, note, access) 
			VALUES ('".$_SESSION['id']."', FROM_UNIXTIME('$date'), $startTime, $endTime, '$priority', 
			'$note', '$level')";
	}
	else
	{
		//what if sid is an array as below??!!!!  please fixme
		$query = "INSERT INTO event (owner, date, startTime, endTime, priority, note, access, sid) 
			VALUES ('".$_SESSION['id']."', FROM_UNIXTIME('$date'), $startTime, $endTime, '$priority', 
			'$note', '$level','$sid')";	
	}
	
	if(is_array($sid))
	{
		$hasAuth = false;
		foreach($sid as $campusID)
		{
			if($this->checkSecurity($level, $campusID) > 1) //check has permission to write
				$hasAuth = true;		
		}
		if($hasAuth)
		{
			$this->db->dbDoQuery($query);
			$newEventID =  $this->db->dbInsertId();
			$this->addPerms($newEventID, $userGroup, $sid);
		}
		else
			error(35); //do not have permission to add event
	}
	else if($this->checkSecurity($level, $sid) > 1) //check has permission to write
	{
		$this->db->dbDoQuery($query);
	}
	else
		error(35); //do not have permission to add event
}

function modSingleEvent($id, $date, $startTime,  $endTime, $priority, $note, $level = 1, $sid = 0, $userGroup = 0)
{
	$query = "UPDATE event SET date=FROM_UNIXTIME('$date'), startTime=$startTime, 
		endTime=$endTime, priority='$priority', note='$note', access='$level', sid='$sid' 
		WHERE id='$id'";
	
	if(is_array($sid))
	{
		$hasAuth = false;
		foreach($sid as $campusID)
		{
			if($this->checkSecurity($level, $campusID) > 2 || ($this->checkOwnership($id, 1) == $_SESSION['id'])) 
				$hasAuth = true;		
		}
		if($hasAuth)
		{
			$this->db->dbDoQuery($query);
			$this->delPerms($id);
			$this->addPerms($id, $userGroup, $sid);
		}
		else
			error(34); //do not have permission to add event
	}
	else
	if(($this->checkSecurity($level, $sid) > 2) || ($this->checkOwnership($id, 1) == $_SESSION['id']))
	{
		$this->db->dbDoQuery($query);
		$this->delPerms($id);
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
		$this->delPerms($id);
	}
	else
		error(34); //do not have permission to modify event

}



function addWeeklyEvent($day, $startTime, $endTime, $startDuration, $endDuration, $priority, $note, $level = 1, $sid = 0)
{
	$query = "INSERT INTO eventWeekly 
			(owner, day, startTime, endTime, startDuration, endDuration, priority, note, access, sid) 
		VALUES 
			('".$_SESSION['id']."', '$day', $startTime, $endTime, FROM_UNIXTIME('$startDuration'), 
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
	$query = "UPDATE eventWeekly SET day='$day', startTime=$startTime, 
		endTime=$endTime, startDuration=FROM_UNIXTIME('$startDuration'), endDuration=FROM_UNIXTIME('$endDuration'),
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
			('".$_SESSION['id']."', '$day', '$dayOcc', $startTime, $endTime, FROM_UNIXTIME('$startDuration'), 
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
	$query = "UPDATE eventMonthly SET day='$day', dayOcc='$dayOcc', startTime=$startTime, 
		endTime=$endTime, startDuration=FROM_UNIXTIME('$startDuration'), endDuration=FROM_UNIXTIME('$endDuration'),
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
			('".$_SESSION['id']."', '$date', $startTime, $endTime, FROM_UNIXTIME('$startDuration'), 
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
	$query = "UPDATE eventMonthly SET date='$date', startTime=$startTime, 
		endTime=$endTime, startDuration=FROM_UNIXTIME('$startDuration'), endDuration=FROM_UNIXTIME('$endDuration'),
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
	{
		$query = "SELECT * FROM eventWeekly WHERE id = '$id'";
		$event = $this->db->dbGetSingleRow($query);
	}
	else if($type == "month")
	{
		$query = "SELECT * FROM eventMonthly WHERE id = '$id'";
		$event = $this->db->dbGetSingleRow($query);
	}
	else
	{
		$query = "SELECT * FROM event WHERE id = '$id'";
		$event = $this->db->dbGetSingleRow($query);
		if($event['access'] == 3)
		{
			unset($event['sid']);
			$query = "SELECT campusID FROM eventCampus WHERE eventID = '$id'";
			$this->db->dbDoQuery($query);
			while($thisCampus = $this->db->dbGetNextArray())
			{
				$event['sid'][] = $thisCampus['campusID'];
			}
			$query = "SELECT groupID FROM eventGroup WHERE eventID = '$id'";
			$this->db->dbDoQuery($query);
			while($thisGroup = $this->db->dbGetNextArray())
			{
				$event['userGroup'][] = $thisGroup['groupID'];
			}
		}
	}
	return $event;
}

function getDayEvents($day, $month, $year, $campus = 0, $group = 0)
{
	//this function will return all events occuring on the date specified
		
	//todays date stamp
	$datestamp = mktime(0,0,0,$month,$day,$year);
	$dayno = date("w", $datestamp)+1;
	$dayocc = ceil($date/7);  
	$eventCount = 0;
	
	if($group)
		$groupID = group;
	else
		$groupID = $_SESSION['gid'];
	//ouput only selected campus
	if($campus)
		$campuslist = $campus;
	else
		$campuslist = implode(',',$_SESSION['campus']);
		
	//give people with userAccess to moderate Calendar Access to view all Camups Posts
	//
	if($this->checkSecurity(3, $_SESSION['campus'][0]) > 2 || $_SESSION['su'])
		$groupChunk = "";
	else
		$groupChunk = "AND eventGroup.groupID = '$groupID'";
		
	//get single events for the day
	$querysingle = "SELECT DISTINCT id, owner,TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_TO_SEC(startTime) as startTimestamp, TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note, access, eventCampus.campusID as sid
	FROM event 
	LEFT JOIN eventCampus ON event.id = eventCampus.eventID
	LEFT JOIN eventGroup ON event.id = eventGroup.eventID
	WHERE date = FROM_UNIXTIME('$datestamp')
	AND ( (access = '4') OR (access = '1' AND owner= '".$_SESSION['id']."') 
		OR (access = '3' AND eventCampus.campusID IN($campuslist) $groupChunk ))
	ORDER BY event.startTime, event.endTime";

	//get weekly events that occur today
	$queryweekly = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_TO_SEC(startTime) as startTimestamp,
	TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note FROM eventWeekly WHERE day = '$dayno' 
	AND startDuration < FROM_UNIXTIME('$datestamp') AND endDuration > FROM_UNIXTIME('$datestamp')
	AND ( (access = '4') OR (access = '1' AND owner= '".$_SESSION['id']."') 
		OR (access = '3' AND sid IN($campuslist)))
	ORDER BY eventWeekly.startTime, eventWeekly.endTime";

	//get monthly events that occur today
	$querymonthly = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_TO_SEC(startTime) as startTimestamp,
	TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note FROM eventMonthly WHERE
	((day = '$dayno' AND dayocc='$dayocc') OR (date='$day'))  
	AND startDuration < FROM_UNIXTIME('$datestamp') AND endDuration > FROM_UNIXTIME('$datestamp')
	AND ( (access = '4') OR (access = '1' AND owner= '".$_SESSION['id']."') 
		OR (access = '3' AND sid IN($campuslist)))
	ORDER BY eventMonthly.startTime, eventMonthly.endTime";

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
	$querysingle = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_TO_SEC(startTime) as startTimestamp, TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note, access, userAccesss.sid 
	FROM event, userAccess WHERE 
	date = FROM_UNIXTIME('$datestamp') AND access = '2' AND uid = ".$_SESSION['id']." 
	AND service = '1' AND sid = '$sid' AND userAccess.sid = event.sid
	ORDER BY event.startTime, event.endTime";

	//get weekly events that occur today
	$queryweekly = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_TO_SEC(startTime) as startTimestamp, TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note, access, userAccess.sid 
	FROM eventWeekly, userAccess WHERE day = '$dayno' 
	AND startDuration < FROM_UNIXTIME('$datestamp') AND endDuration > FROM_UNIXTIME('$datestamp')
	AND uid = ".$_SESSION['id']." AND access = '2' AND service = '1' AND sid = '$sid' AND userAccess.sid = event.sid
	ORDER BY eventWeekly.startTime, eventWeekly.endTime";

	//get monthly events that occur today
	$querymonthly = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_TO_SEC(startTime) as startTimestamp, TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note, access, userAccess.sid 
	FROM eventMonthly, userAccess WHERE
	((day = '$dayno' AND dayocc='$dayocc') OR (date='$day'))  
	AND startDuration < FROM_UNIXTIME('$datestamp') AND endDuration > FROM_UNIXTIME('$datestamp')
	AND uid = ".$_SESSION['id']." AND access = '2' AND service = '1' AND sid = '$sid' AND userAccess.sid = event.sid
	ORDER BY eventMonthly.startTime, eventMonthly.endTime";


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
	$querysingle = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_TO_SEC(startTime) as startTimestamp, TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note, access, userAccess.sid
	FROM event, userAccess WHERE 
	date = FROM_UNIXTIME('$datestamp') AND uid = ".$_SESSION['id']." AND access = '2' 
	AND service = '1' AND userAccess.sid = event.sid
	ORDER BY event.startTime, event.endTime";

	//get weekly events that occur today
	$queryweekly = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_TO_SEC(startTime) as startTimestamp, TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note, access, userAccess.sid 
	FROM eventWeekly, userAccess WHERE day = '$dayno' 
	AND startDuration < FROM_UNIXTIME('$datestamp') AND endDuration > FROM_UNIXTIME('$datestamp')
	AND uid = ".$_SESSION['id']." AND access = '2' AND service = '1' AND userAccess.sid = eventWeekly.sid
	ORDER BY eventWeekly.startTime, eventWeekly.endTime";

	//get monthly events that occur today
	$querymonthly = "SELECT id, owner, TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTime, TIME_TO_SEC(startTime) as startTimestamp, TIME_FORMAT(endTime, '".CALENDAR_time_format."') as endTime, priority, note, access, userAccess.sid 
	FROM eventMonthly, userAccess WHERE
	((day = '$dayno' AND dayocc='$dayocc') OR (date='$day'))  
	AND startDuration < FROM_UNIXTIME('$datestamp') AND endDuration > FROM_UNIXTIME('$datestamp')
	AND uid = ".$_SESSION['id']." AND access = '2' AND service = '1' AND userAccess.sid = eventMonthly.sid
	ORDER BY eventMonthly.startTime, eventMonthly.endTime";


	return $this->getCalendarArray($querysingle, $queryweekly, $querymonthly);
	
}

function getSubmissionEvents($day, $month, $year)
{

	//get a list of all the possible submission box categories
	require_once($_SERVER['DOCUMENT_ROOT']."/include/involved.php");
	$catList = involvedCategoryList($_SESSION['id'], $_SESSION['subjects'], 1);

	if(is_array($catList))
	{
		$start_date = mktime(0,0,0,$month,$day,$year);
		$end_date = mktime(23,59,59,$month,$day,$year); 

		foreach($catList as $cat)
		{
			$query = "SELECT category.name AS category, category.code AS synergyCode, submission_box.description, submission_box.owner,
				DATE_FORMAT(submission_box.date, '%w') as dayIndex, DATE_FORMAT(submission_box.date, '".CALENDAR_time_format."') as dueTime
				FROM category, file, submission_box WHERE category.id = submission_box.category 
				AND file.id = submission_box.fid AND submission_box.category = {$cat['id']} AND submission_box.deleted = 0 AND
				submission_box.date >= FROM_UNIXTIME('$start_date') AND submission_box.date <= FROM_UNIXTIME('$end_date')
				ORDER BY submission_box.date ";
			$this->db->dbDoQuery($query);
			while($subEvent = $this->db->dbGetNextArray())
			{ 		
				$subEvents['category'][] = $subEvent['category'];
				$subEvents['owner'][] = $subEvent['owner'];
				$subEvents['description'][] = $subEvent['description'];
				$subEvents['synergyCode'][] = $subEvent['synergyCode'];
				$subEvents['dueTime'][] = $subEvent['dueTime'];
			}
		}
	}

	return $subEvents;
}

function getStudentBirthdays($day, $month, $year)
{
	if(SYNERGY_enable) {
        $db = mssql_connect(MSSQL_host,MSSQL_user, MSSQL_passwd);

        $query = "SELECT  vStudents.StudentID, vStudents.StudentGiven1, vStudents.StudentSurname,
                        vStudents.StudentBirthDate, vStudents.StudentCampus, datediff(year,studentbirthdate,getdate()) as 'Student Age',
                        vStudents.StudentFormDescription, convert(varchar,studentbirthdate,106)  as 'la'
                        FROM SynergyOne.dbo.vStudents vStudents
                        WHERE (vStudents.CurrentSemesterOnlyFlag=1)
                        AND day(studentbirthdate) = ". $day ."
                        AND month(studentbirthdate) =". $month ."
                        AND StudentCampus = 'G'
                        ORDER BY day(studentbirthdate) ";

        $result = mssql_query($query, $db);
        $i=0;
        if($result != 1)
        {
                while($line = mssql_fetch_assoc($result))
                {

                        $birthdays[$i]['name'] = $line['StudentGiven1']." ".$line['StudentSurname'];
                        $birthdays[$i]['form'] = $line['StudentFormDescription'];
                        $birthdays[$i]['age'] = $line['Student Age'];
                        $birthdays[$i]['birthdate'] = $line['la'];
                        $i++;
                }
		return $birthdays;
        }
	}
}


function getWeekSubmissionEvents($day, $month, $year)
{

	//get a list of all the possible submission box categories
	require_once($_SERVER['DOCUMENT_ROOT']."/include/involved.php");
	$catList = involvedCategoryList($_SESSION['id'], $_SESSION['subjects'], 1);

	if(is_array($catList))
	{
		$start_date = mktime(0,0,0,$month,$day,$year);
		$end_date = mktime(23,59,59,$month,$day+7,$year); 

		foreach($catList as $cat)
		{
			$query = "SELECT category.name AS category, category.code AS synergyCode, submission_box.description, submission_box.owner,
				DATE_FORMAT(submission_box.date, '%w') as dayIndex, DATE_FORMAT(submission_box.date, '".CALENDAR_time_format."') as dueTime
				FROM category, file, submission_box WHERE category.id = submission_box.category 
				AND file.id = submission_box.fid AND submission_box.category = {$cat['id']} AND submission_box.deleted = 0 AND
				submission_box.date >= FROM_UNIXTIME('$start_date') AND submission_box.date <= FROM_UNIXTIME('$end_date')
				ORDER BY submission_box.date ";
			$this->db->dbDoQuery($query);
			while($subEvent = $this->db->dbGetNextArray())
			{ 		
				$subEvents[$subEvent['dayIndex']]['category'][] = $subEvent['category'];
				$subEvents[$subEvent['dayIndex']]['owner'][] = $subEvent['owner'];
				$subEvents[$subEvent['dayIndex']]['description'][] = $subEvent['description'];
				$subEvents[$subEvent['dayIndex']]['synergyCode'][] = $subEvent['synergyCode'];
				$subEvents[$subEvent['dayIndex']]['dueTime'][] = $subEvent['dueTime'];
			}
		}
	}

	return $subEvents;
}

function getCalendarArray($dayQuery, $weekQuery, $monthQuery)
{

	$eventCount = 0;
	$this->db->dbDoQuery($dayQuery);
	while( $line = $this->db->dbGetNextArray() )
	{
		$line['type'] = 'day';
		$dayEvents[] = $line;
	}

	$this->db->dbDoQuery($weekQuery);
	while( $line = $this->db->dbGetNextArray() )
	{
		$line['type'] = 'week';
		$dayEvents[] = $line;
	}

	$this->db->dbDoQuery($monthQuery);
	while( $line = $this->db->dbGetNextArray() )
	{
		$line['type'] = 'month';
		$dayEvents[] = $line;
	}

	return $dayEvents;
}

} ?>
