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
		if(!is_array($this->colourSet) ) {
			//build cache of colours on first call
			$query = "SELECT eventTypeID, eventColour FROM luEventType";
			$result = $this->db->query($query);
			while($row = $this->db->getNextArray($result) ) {
				$this->colourSet[$row['eventTypeID']] = '#'.$row['eventColour'];
			}
		}
		return $this->colourSet[$id];
		
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

	function getTypeList() {
		$query = "SELECT eventTypeID, eventType FROM luEventType";
		return $this->db->getAll($query);
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
		return $groupList = $this->db->getAll($query);

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
		$query = "SELECT id, owner, access, sid, type, note, date,
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
		'".CALENDAR_time_format."') as endTimeString,type, note
				  FROM event WHERE access = '2' AND sid = '".$categoryID."' AND( date >= CURRENT_DATE() $where) ORDER BY date, startTime";
		
		return $this->db->getAll($query);
	}

	function getSubmissionEvents($currentDay) {
	
		//get a list of all the possible submission box categories		
		$catList = $this->groupHandler->involvedCategoryList($_SESSION['id'], $_SESSION['subjects'], 1);
		
		if(is_array($catList)) {
			foreach($catList as $cat)
				$catQuery[] = $cat['id'];
		} else
			$catQuery = array('NULL');
			
		$query = "SELECT DISTINCT category.name AS category, GROUP_CONCAT(co.code) AS synergyCode, submission_box.description, submission_box.owner,
					DATE_FORMAT(submission_box.date, '%w') as dayIndex, 
					DATE_FORMAT(submission_box.date, '" . CALENDAR_time_format . "') as dueTime
				  FROM (category, submission_box )
				  LEFT JOIN `file` f ON f.id = submission_box.fid 
				  LEFT JOIN categoryCode co ON co.categoryID = category.id
				  WHERE category.id = submission_box.category 
					AND submission_box.category IN (".implode(',', $catQuery).")
					AND submission_box.deleted = 0 
					AND	DATE(submission_box.date) = DATE(FROM_UNIXTIME('$currentDay'))
				  GROUP BY submission_box.id, category.id
				  ORDER BY submission_box.date ";
				
		return $this->db->getAll($query);

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
				$groupList[] = $temp['id'];
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
                            TIME_TO_SEC(startTime) as startTimestamp,
                            TIME_TO_SEC(endTime) as endTimestamp,
							type, note, access, eventCampus.campusID as sid
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
	
	function getDayByGroup($currentDay, $groupList, $campusList) {
		
		$query = "SELECT DISTINCT id, owner,
							TIME_FORMAT(startTime, '" . CALENDAR_time_format . "') as startTime, 
							TIME_FORMAT(endTime, '" . CALENDAR_time_format . "') as endTime,
							type, note, access, eventCampus.campusID as sid
						FROM event 
						LEFT JOIN eventCampus ON event.id = eventCampus.eventID
						LEFT JOIN eventGroup ON event.id = eventGroup.eventID
						WHERE date = '" . date("Y-m-d", $currentDay) . "'
							AND (    
								  (access = '3' AND eventCampus.campusID IN(" . implode(',', $campusList) . ") AND eventGroup.groupID IN(" . implode(',', $groupList) . ") )
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