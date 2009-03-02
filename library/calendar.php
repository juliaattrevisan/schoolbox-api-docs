<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

class Calendar extends Model {

	function Calendar () {
	
		$this->_table = "event";
		$this->_index = "id";
		
		$this->groupHandler = new Group(CALENDAR_SERVICE);
		
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


	function getPerm($eventID) {
		
		if (is_array($eventID))
			$event =& $eventID;
		else
			$event = $this->getByID($eventID);
	
		if ($_SESSION['su'])
			return 3;
	
		switch ($event['access']) {
		
			case 1: // personal
				return $event['owner'] == $_SESSION['id'] ? 2 : 0;
				
			case 2: // single group
				$this->groupHandler->setService(HOMEPAGE_SERVICE);
				$permission = $this->groupHandler->getPermission($event['sid']);
				$this->groupHandler->setService(CALENDAR_SERVICE);
				return $permission;
				
			case 3: // campus
				
				if (is_array($event['campusList']))
					$campusList = $event['campusList'];
				else
					$campusList = $this->getEventCampus($event['id']);
						
				if(is_array($campusList)) {
					foreach($campusList as $campus) {
						$permission = $this->groupHandler->getPermission($campus);
						if($output < $permission || !$output)
							$output = $permission;
					}		
				}

				return $output;
				
			default:
				return 0;
		}
	}
	
	function getPriorityList() {
		$priority = array(array('PRIORITY_NUMBER' => 4, 
								'PRIORITY_NAME'	=> 'Low (Black)'),
						  array('PRIORITY_NUMBER' => 3, 
						  		'PRIORITY_NAME'	=> 'Medium (Blue)'),
						  array('PRIORITY_NUMBER' => 2, 
						  		'PRIORITY_NAME'	=> 'High (Green)'),
						  array('PRIORITY_NUMBER' => 1, 
						  		'PRIORITY_NAME'	=> 'Very High (Red)'),
						  );
		return $priority;
	}


	function getCampusList($type = 1) {
		$query = "SELECT id, name FROM luCampus";
		if (!$_SESSION['su']) {
			$query .=", userAccess WHERE luCampus.id = userAccess.sid AND 
				userAccess.service = '2' AND uid = ".$_SESSION['id']." AND type > $type";   
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

	function getCategoryList($type = 1){
		//get a list of all groups the user has write access to
		$query = "SELECT id, name FROM userAccess, category WHERE uid = '".$_SESSION['id']."' AND service = '1' AND type > $type AND sid = id";   
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

	function getGroupList() {
		$query = "SELECT * FROM groups";
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
	
	function getAllInCategory($categoryID, $config = false) {

		//event limit changed from posts to days
		if($config['size'] != 0 && $config['size'] != '')
				$where = " AND date < ADDDATE(CURDATE(), INTERVAL {$config['size']} DAY) ";
		else
				$where = "";
	
		$query = "SELECT id, DATE_FORMAT(date, '%a %D %b') as outputDate,
						DATE_FORMAT(date, '%Y') as year, DATE_FORMAT(date, '%c') as month, DATE_FORMAT(date, '%e') as day,
						TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTimeString, TIME_FORMAT(endTime, 
		'".CALENDAR_time_format."') as endTimeString,priority, note
				  FROM event WHERE access = '2' AND sid = '".$categoryID."' AND( date >= CURRENT_DATE() $where) ORDER BY date, startTime";
		
		return $this->db->getAll($query);
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



	function getDayEvents($currentDay) {
		// return all events occuring on the date specified

		if(is_array($_SESSION['campus']))
			$campusList = $_SESSION['campus'];
		else
			$campusList = array(1);
		
		
		// get accessible groups
		$groupList = array();
		$groupQuery = '';
		$tempList = $this->getCategoryList();
		if (is_array($tempList)) {
			foreach ($tempList as $temp) {
				$groupList[] = $temp['GROUP_ID'];
			}
			$groupQuery = "OR (access = '2' AND sid IN (".implode(',', $groupList)."))";
		}
		
		//give people with userAccess to moderate Calendar Access to view all Camups Posts
		if(is_array($_SESSION['campus'])) {
			if($this->groupHandler->getPermission($_SESSION['campus'][0]) > WRITE_ACCESS)
				$groupChunk = "";
			else {
				$groupChunk = "AND eventGroup.groupID = {$_SESSION['gid']}";
			}
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
							AND (    (access = '1' AND owner = '".$_SESSION['id']."')
								  $groupQuery
								  OR (access = '3' AND eventCampus.campusID IN(" . implode(',', $campusList) . ") $groupChunk )
								  OR (access = '4')
								)
						GROUP BY id
						ORDER BY event.startTime, event.endTime";
		
		return $this->db->getAll($query);	

	}		

	function getStudentBirthdays($currentDay) {
	
		require_once '../library/mssql.php';	
		$db = new MSSQL;
		
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
	
		$birthdayList = $db->getAll($query);
		if (is_array($birthdayList)) {
			$i = 0;
			foreach ($birthdayList as $birthday) {
				$output[$i]['name'] = $birthday['StudentGiven1']." ".$birthday['StudentSurname'];
				$output[$i]['form'] = $birthday['StudentFormDescription'];
				$output[$i]['age'] 	= $birthday['age'];
				$output[$i]['birthdate'] = $birthday['la'];
				$i++;		
			}
		}
		
		return $output;
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
		
		if($this->getPerm($event) < WRITE_ACCESS)
			error(35);

		$groupList = $event['groupList'];
		$campusList = $event['campusList'];
		
		unset($event['groupList']);
		unset($event['campusList']);

		$eventID = parent::create($event);	
		
		$this->createGroups($groupList, $eventID);
		$this->createCampus($campusList, $eventID);
				
		return $eventID;
	}
	
	function update($event) {
		
		if($this->getPerm($event['id']) < WRITE_ACCESS)
			error(34);

		$groupList = $event['groupList'];
		$campusList = $event['campusList'];

		unset($event['groupList']);
		unset($event['campusList']);
		
		parent::update($event);	
		
		$this->delPerms($event['id']);
		
		$this->createGroups($groupList, $event['id']);
		$this->createCampus($campusList, $event['id']);		
				
		return $eventID;	
	}
	
	function delete ($eventID) {
		$event = $this->getByID($eventID);

		if($this->getPerm($eventID) < WRITE_ACCESS)
			error(34);

		parent::delete($eventID);
		$this->delPerms($eventID);
	}

}

?>