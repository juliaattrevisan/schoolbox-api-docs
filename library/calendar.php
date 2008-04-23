<?php

require_once '../include/model.php';

class Calendar extends Model {

	function Calendar () {
	
		
		$this->_table = "event";
		$this->_index = "id";
		
		parent::Model();
	}

	function displayColour($id) {
		switch ($id) {
			case 4:
				return "#000000"; //black
			case 3:
				return "#0033FF"; //blue  
			case 2:
				return "#009933"; //green
			case 1:
				return "#FF0000"; //red
			default:
				return "#000000";
		}
	}


	function checkSecurity($level, $id) {
		// check user has rights to post a message with level above personal
		// returns 1 on read, 2 on write (can modify own) and 3 on moderate	
	
		switch ($level) {
		
			case 1: // personal
				return 2;
			case 2: // group
				if(is_array($id)) {
					foreach($id as $serv) {
						$value = perm($serv, 1);
						if($value >= $perm)
							$perm = $value;
					}		
				}
				else
					$perm = perm($id, 1);
				return $perm;
				
			case 3: // campus
				if(is_array($id)) {
					foreach($id as $serv) {
						$value = perm($serv, 2);
						if($value >= $perm)
							$perm = $value;
					}		
				}
				else
					$perm = perm($id, 2);
				return $perm;

			case 4: // all
				return perm(0, 2);
				
			default:
				return 0;
		}
	}
	
	function getPriorityList() {
		$priority = array(array('PRIORITY_NUMBER' => 4, 'PRIORITY_NAME'	=> 'Low (Black)'),
						  array('PRIORITY_NUMBER' => 3, 'PRIORITY_NAME'	=> 'Medium (Blue)'),
						  array('PRIORITY_NUMBER' => 2, 'PRIORITY_NAME'	=> 'High (Green)'),
						  array('PRIORITY_NUMBER' => 1, 'PRIORITY_NAME'	=> 'Very High (Red)'),
						  );
		return $priority;
	}


	function getCampusList() {
		$query = "SELECT id, name FROM luCampus";
		if (!$_SESSION['su']) {
			$query .=", userAccess WHERE luCampus.id = userAccess.sid AND 
				userAccess.service = '2' AND uid = ".$_SESSION['id']." AND type > '1'";   
		}
		$campusList = $this->db->getAll($query);
		if (is_array($campusList)) {
			foreach ($campusList as $campus) {
				$data['CAMPUS_NAME'] = $campus['name'];
				$data['CAMPUS_ID'] 	= $campus['id'];
				$output[] = $data;
			}
		}
		return $output;
	}  

	function getGroupList(){
		//get a list of all groups the user has write access to
		$query = "SELECT id, name FROM userAccess, category WHERE uid = '".$_SESSION['id']."' AND service = '1' AND type > 1 AND sid = id";   
		$groupList = $this->db->getAll($query);
		if (is_array($groupList)) {
			foreach ($groupList as $group) {
				$data['GROUP_ID'] 	= $group['id'];
				$data['GROUP_NAME'] = $group['name'];
				$output[] = $data;
			}
		}
		return $output;
	}  

	function getByID($eventID) {
		$query = "SELECT id, owner, access, sid, priority, note, date, 
						DATE_FORMAT(startTime, '" . CALENDAR_time_format . "') startTime, 
						DATE_FORMAT(endTime, '" . CALENDAR_time_format . "') endTime
				  FROM " . $this->_table . "
				  WHERE " . $this->_index . " = $eventID";
		return $this->db->getRow($query);
	}


	function getWeekSubmissionEvents($currentDay) {
	
		//get a list of all the possible submission box categories
		require_once($_SERVER['DOCUMENT_ROOT']."/include/involved.php");
		$catList = involvedCategoryList($_SESSION['id'], $_SESSION['subjects'], 1);
	
		if(is_array($catList))
		{
			$start_date = $currentDay;
			$end_date = strtotime("+7 days", $currentDay); 
	
			foreach($catList as $cat)
			{
				$query = "SELECT category.name AS category, category.code AS synergyCode, submission_box.description, submission_box.owner,
							DATE_FORMAT(submission_box.date, '%w') as dayIndex, 
							DATE_FORMAT(submission_box.date, '" . CALENDAR_time_format . "') as dueTime
						  FROM category, file, submission_box 
						  WHERE category.id = submission_box.category 
							AND file.id = submission_box.fid 
							AND submission_box.category = {$cat['id']} 
							AND submission_box.deleted = 0 
							AND	submission_box.date >= FROM_UNIXTIME('$start_date') 
							AND submission_box.date <= FROM_UNIXTIME('$end_date')
						  ORDER BY submission_box.date ";
				$this->db->query($query);
				while($subEvent = $this->db->getNextArray())
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

	function getSubmissionEvents($currentDay) {
	
		//get a list of all the possible submission box categories
		require_once($_SERVER['DOCUMENT_ROOT']."/include/involved.php");
		$catList = involvedCategoryList($_SESSION['id'], $_SESSION['subjects'], 1);
	
		if(is_array($catList)) {
	
			foreach($catList as $cat) {
				$query = "SELECT category.name AS category, category.code AS synergyCode, submission_box.description, submission_box.owner,
							DATE_FORMAT(submission_box.date, '%w') as dayIndex, 
							DATE_FORMAT(submission_box.date, '" . CALENDAR_time_format . "') as dueTime
						  FROM category, file, submission_box 
						  WHERE category.id = submission_box.category 
							AND file.id = submission_box.fid 
							AND submission_box.category = {$cat['id']} 
							AND submission_box.deleted = 0 
							AND	submission_box.date >= FROM_UNIXTIME('$currentDay') 
							AND submission_box.date <= FROM_UNIXTIME('$currentDay')
						  ORDER BY submission_box.date ";
				$subEvents[] = $this->db->getAll($query);
			}
		}
		return $subEvents;
	}

	function getAllDayEvents($currentDay) {
	
		//gets normal events and group shared events and merges them into an one array
		$normalevents = $this->getDayEvents($currentDay);
		$groupevents =  $this->getAllGroupDayEvents($currentDay);
	
		if(is_array($normalevents) && is_array($groupevents))
			$event = array_merge($normalevents,$groupevents);
		else if(is_array($normalevents))
			$event = $normalevents;
		else if(is_array($groupevents))
			$event = $groupevents;
		else
			return false;
		
		//sort array on start time
		usort($event,array($this,"timecmp"));
		
		return $event;
	}
	
	function timecmp($a, $b) {
		
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

	function getDayEvents($currentDay) {
		// return all events occuring on the date specified

		if(is_array($_SESSION['campus']))
			$campuslist = implode(',',$_SESSION['campus']);
		else
			$campuslist = 1;
		
		
		// get accessible groups
		
		
		//give people with userAccess to moderate Calendar Access to view all Camups Posts
		if(is_array($_SESSION['campus'])) {
			if($this->checkSecurity(3, $_SESSION['campus'][0]) > 2 || $_SESSION['su'])
				$groupChunk = "";
			else
				$groupChunk = "AND eventGroup.groupID = '$groupID'";
		}
			
		//get single events for the day
		$query = "SELECT DISTINCT id, owner,
							TIME_FORMAT(startTime, '" . CALENDAR_time_format . "') as startTime, 
							TIME_FORMAT(endTime, '" . CALENDAR_time_format . "') as endTime,
							priority, note, access, eventCampus.campusID as sid
						FROM event 
						LEFT JOIN eventCampus ON event.id = eventCampus.eventID
						LEFT JOIN eventGroup ON event.id = eventGroup.eventID
						WHERE date = '" . date("Y-m-d", $currentDay) . "'
							AND ( (access = '4') OR (access = '1' AND owner= '".$_SESSION['id']."') 
								OR (access = '3' AND eventCampus.campusID IN($campuslist) $groupChunk ))
						GROUP BY id
						ORDER BY event.startTime, event.endTime";
	
		return $this->db->getAll($query);	

	}		

	function getAllGroupDayEvents($currentDay) {
		// return all events occuring in the users groups on the date specified

		$query = "SELECT id, owner, TIME_FORMAT(startTime, '" . CALENDAR_time_format . "') as startTime, 
							TIME_FORMAT(endTime, '" . CALENDAR_time_format . "') as endTime,
							priority, note, access, userAccess.sid
						FROM event, userAccess 
						WHERE date = '" . date("Y-m-d", $currentDay) . "'
							AND uid = '".$_SESSION['id']."' 
							AND access = '2' 
							AND service = '1' 
							AND userAccess.sid = event.sid
						ORDER BY event.startTime, event.endTime";
	
		
		return $this->db->getAll($query);
	
	}	

	function getCalendarArray($dayQuery, $weekQuery, $monthQuery) {
	
		$eventCount = 0;
		$this->db->query($dayQuery);
		while( $line = $this->db->getNextArray() )
		{
			$line['type'] = 'day';
			$dayEvents[] = $line;
		}
	
		$this->db->query($weekQuery);
		while( $line = $this->db->getNextArray() )
		{
			$line['type'] = 'week';
			$dayEvents[] = $line;
		}
	
		$this->db->query($monthQuery);
		while( $line = $this->db->getNextArray() )
		{
			$line['type'] = 'month';
			$dayEvents[] = $line;
		}
	
		return $dayEvents;
	}		

	function getStudentBirthdays($currentDay) {
	
		
		$db = mssql_connect(MSSQL_host,MSSQL_user, MSSQL_passwd); 
		
		$query = "SELECT  vStudents.StudentID, vStudents.StudentGiven1, vStudents.StudentSurname,
						vStudents.StudentBirthDate, vStudents.StudentCampus, datediff(year,studentbirthdate,getdate()) as age,
						vStudents.StudentFormDescription, convert(varchar,studentbirthdate,106)  as 'la'
						FROM SynergyOne.dbo.vStudents vStudents
						WHERE 
						vStudents.FileSemester=".$_SESSION['FileSemester']." 
						AND vStudents.FileYear=".$_SESSION[ 'FileYear']." 
						AND day(studentbirthdate) = ". date("j", $currentDay) ."
						AND month(studentbirthdate) =". date("n", $currentDay) ."
						AND StudentCampus = 'G'";
	
		$result = mssql_query($query, $db);
		$i=0;
		if($result !== FALSE && $result !== TRUE) {
			while($line = mssql_fetch_assoc($result)) {
				$birthdays[$i]['name'] = $line['StudentGiven1']." ".$line['StudentSurname'];
				$birthdays[$i]['form'] = $line['StudentFormDescription'];
				$birthdays[$i]['age'] = $line['age'];
				$birthdays[$i]['birthdate'] = $line['la'];
				$i++;
			}
		return $birthdays;
		}
	}

	function getEventGroup($eventID) {
		$output = array();
		$query = "SELECT groupID FROM eventGroup WHERE eventID = $eventID";
		$result = $this->db->query($query);
		while($event = $this->db->getNextArray($result)) {
			$output[] = $event['groupID'];
		}
		return $output;
	}

	function getEventCampus($eventID) {
		$output = array();
		$query = "SELECT campusID FROM eventCampus WHERE eventID = $eventID";
		$result = $this->db->query($query);
		while($event = $this->db->getNextArray($result)) {
			$output[] = $event['campusID'];
		}
		return $output;
	}

	function getMonthList() {
		for($i=1; $i <= 12; $i++) {
			$output['DROP_MONTH_NUMBER'] = $i;
			$output['DROP_MONTH_NAME'] = date('F', mktime(0,0,0,$i,1));
			$monthList[] = $output;
		}
		return $monthList;
	}

	function getYearList() {
		for($i=date('Y')-2; $i <= date('Y')+1; $i++) {
			$output['DROP_YEAR_NUMBER'] = $i;
			$yearList[] = $output;
		}
		return $yearList;
	}

	function createGroups($groupList, $eventID) {
		if (is_array($groupList)) {
			foreach ($groupList as $groupID) {
				$eventGroup = array('eventID'	=> $eventID,
									'groupID'	=> $groupID,
									);
				$this->db->insert('eventGroup', $eventGroup);
			}
		}	
	}
	
	function createCampus($campusList, $eventID) {
		if (is_array($campusList)) {
			foreach ($campusList as $campusID) {
				$eventCampus = array('eventID'	=> $eventID,
									 'campusID'	=> $campusID,
									);
				$this->db->insert('eventCampus', $eventCampus);
			}
		}	
	}
	
	function delPerms($eventID) {
		$this->db->delete('eventGroup', 'eventID', $eventID);
		$this->db->delete('eventCampus', 'eventID', $eventID);
	}
	

	
	
	function create($event) {
		
		$hasAuth = true;
		
		// group permissions
		if (is_array($event['group'])) {
			$groupList = $event['group'];
			foreach ($groupList as $groupID) {
				if(!$this->checkSecurity($event['access'], $groupID))
					$hasAuth = false;
			}
		}

		// campus permissions
		if ($hasAuth && is_array($event['campus'])) {
			$campusList = $event['campus'];
			foreach ($campusList as $campusID) {
				if(!$this->checkSecurity($event['access'], $campusID))
					$hasAuth = false;
			}
		}
			
		if (!$hasAuth)
			error(35);

		unset($event['group']);
		unset($event['campus']);

		$eventID = parent::create($event);	
		
		$this->createGroups($groupList, $eventID);
		$this->createCampus($campusList, $eventID);
				
		return $eventID;
	}
	
	function update($event) {
		
		$hasAuth = true;
		
		// group permissions
		if (is_array($event['group'])) {
			$groupList = $event['group'];
			foreach ($groupList as $groupID) {
				if(!$this->checkSecurity($event['access'], $groupID))
					$hasAuth = false;
			}
		}

		// campus permissions
		if (is_array($event['campus'])) {
			$campusList = $event['campus'];
			foreach ($campusList as $campusID) {
				if(!$this->checkSecurity($event['access'], $campusID))
					$hasAuth = false;
			}
		}
		
		if (!$hasAuth)
			error(35);
			
		unset($event['group']);
		unset($event['campus']);
		
		parent::update($event);	
		
		$this->delPerms($event['id']);
		
		$this->createGroups($groupList, $event['id']);
		$this->createCampus($campusList, $event['id']);		
				
		return $eventID;	
	}
	
	function delete ($eventID) {
		parent::delete($eventID);
		$this->delPerms($eventID);
	}

}

?>