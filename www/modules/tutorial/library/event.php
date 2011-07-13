<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
define('TUTORIAL_db', 'tutorial');
define('PORTAL_db', SQL_db);

class Event extends DeprecatedModel {
	function Event() {
		$this->_table = "event";
		$this->_index = "eventID";
		
		$groupHandler = new Group(TUTORIAL_SERVICE);
		$this->permission = $groupHandler->getPermission(0);
		
		parent::DeprecatedModel();
	}
	
	
	function getAll($filter = '', $order = 'name', $dir = 'asc', $userID = false) {
		
		$query = "SELECT e.*, UNIX_TIMESTAMP(e.date) date, TIME_FORMAT(start, '%l:%i %p') startTime, TIME_FORMAT(finish, '%l:%i %p') finishTime, 
					concat(t1.firstname,' ',t1.lastname) tutorName1, concat(t2.firstname,' ',t2.lastname) tutorName2, y.type typeName
				  FROM ".TUTORIAL_db.".".$this->_table." e
				  LEFT JOIN ".TUTORIAL_db.".enrolment n ON (n.code = e.code AND n.code != '') OR n.eventID = e.eventID
				  LEFT JOIN ".PORTAL_db.".user t1 ON t1.id = e.tutor1ID
				  LEFT JOIN ".PORTAL_db.".user t2 ON t2.id = e.tutor2ID 
				  LEFT JOIN ".TUTORIAL_db.".luType y ON y.typeID = e.typeID
				  WHERE 1=1 ";
		
		$filter = strtolower($filter);	  
		if ($filter == 'semester') {
			if (date('m') <= 6)
				$query .= "AND DATE_FORMAT(`date`, '%m') IN (1,2,3,4,5,6) "; // Jan 1 - Jun 30
			else
				$query .= "AND DATE_FORMAT(`date`, '%m') IN (7,8,9,10,11,12) "; // Jul 1 - Dec 31
		}
		elseif ($filter == 'day')
			$query .= "AND DATE(`date`) = CURDATE() ";
		elseif ($filter != 'all')
			$query .= "AND DATE_FORMAT(`date`, '%U') = DATE_FORMAT(NOW(),'%U') ";

		
		if ($this->permission >= FULL_ACCESS && $userID) {
			$query .= "AND (e.tutor1ID = ".$userID." OR e.tutor2ID = ".$userID." OR n.studentID = " . $userID.") ";
		
		} elseif ($this->permission < FULL_ACCESS)
			$query .= "AND (e.tutor1ID = ".$_SESSION['id']." OR e.tutor2ID = ".$_SESSION['id']." OR n.studentID = " . $_SESSION['id'].") ";
			
		$query .= "GROUP BY eventID ";
		
		if ($order)
			$query .= "ORDER BY `" . $order . "` " . ($dir == 'desc' ? 'DESC' : 'ASC');
		
		return $this->db->getAll($query);
	}

	function getHome () {
		
		$query = "SELECT e.*, UNIX_TIMESTAMP(e.date) date, TIME_FORMAT(start, '%l:%i %p') start, TIME_FORMAT(finish, '%l:%i %p') finish, 
					concat(t1.firstname,' ',t1.lastname) tutorName1, concat(t2.firstname,' ',t2.lastname) tutorName2, y.type typeName
				  FROM ".TUTORIAL_db.".".$this->_table." e
				  LEFT JOIN ".TUTORIAL_db.".enrolment n ON (n.code = e.code AND n.code != '') OR n.eventID = e.eventID
				  LEFT JOIN ".PORTAL_db.".user t1 ON t1.id = e.tutor1ID
				  LEFT JOIN ".PORTAL_db.".user t2 ON t2.id = e.tutor2ID 
				  LEFT JOIN ".TUTORIAL_db.".luType y ON y.typeID = e.typeID
				  WHERE DATE_FORMAT(`date`, '%U') = DATE_FORMAT(NOW(),'%U')
				  AND DATE(`date`) >= CURDATE()
				  AND (e.tutor1ID = ".$_SESSION['id']." OR e.tutor2ID = ".$_SESSION['id']." OR n.studentID = " . $_SESSION['id'].") 
				  GROUP BY eventID 
				  ORDER BY date ASC, start ASC";
		
		return $this->db->getAll($query);
	}
	
	function getWeek($week) {
		$query = "SELECT e.*, UNIX_TIMESTAMP(e.date) date, TIME_FORMAT(start, '%l:%i %p') start, TIME_FORMAT(finish, '%l:%i %p') finish, concat(t1.firstname,' ',t1.lastname) tutorName1, concat(t2.firstname,' ',t2.lastname) tutorName2 FROM ".TUTORIAL_db.".".$this->_table." e
				  LEFT JOIN ".TUTORIAL_db.".enrolment n ON n.code = e.code 
				  LEFT JOIN ".PORTAL_db.".user t1 ON t1.id = e.tutor1ID
				  LEFT JOIN ".PORTAL_db.".user t2 ON t2.id = e.tutor2ID 
				  WHERE DATE_FORMAT(`date`, '%Y%U') = $week ";

		if ($this->permission < WRITE_ACCESS)
			$query .= "AND n.studentID = " . $_SESSION['id']." ";
		elseif ($this->permission < FULL_ACCESS)
			$query .= "AND (e.tutor1ID = ".$_SESSION['id']." OR e.tutor2ID = ".$_SESSION['id'].") ";			
		
		$query .= "GROUP BY eventID ORDER BY date ASC";
		
		return $this->db->getAll($query);			
	}
	
	function getStatistics ($filter = '') {
		$query = "SELECT e.*, COUNT( a.studentID) expected, COUNT(IF(a.present='true',1,NULL)) attended, COUNT(DISTINCT e.eventID) classes 
				  FROM ".TUTORIAL_db.".event e
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID
				  WHERE e.typeID = 1 ";
				  
		if ($filter == 'semester') {
			if (date('m') <= 6)
				$query .= "AND DATE_FORMAT(`date`, '%m') IN (1,2,3,4,5,6) "; // Jan 1 - Jun 30
			else
				$query .= "AND DATE_FORMAT(`date`, '%m') IN (7,8,9,10,11,12) "; // Jul 1 - Dec 31
		}
		elseif ($filter == 'week')
			$query .= "AND DATE_FORMAT(`date`, '%U') = DATE_FORMAT(NOW(),'%U') ";
		
		if ($this->permission < FULL_ACCESS)
			$query .= "AND (e.tutor1ID = ".$_SESSION['id']." OR e.tutor2ID = ".$_SESSION['id'].")";
		
		$query .= "GROUP BY e.code ORDER BY e.code, date";
		
		return $this->db->getAll($query);
	}

	function getConsultationStatistics ($filter = '') {
		$query = "SELECT e.*, COUNT( a.studentID) expected, COUNT(IF(a.present='true',1,NULL)) attended, COUNT(DISTINCT e.eventID) classes, COUNT(DISTINCT a.studentID) students  FROM ".TUTORIAL_db.".event e 
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID
				  WHERE e.typeID = 2  ";
				  
		if ($filter == 'semester') {
			if (date('m') <= 6)
				$query .= "AND DATE_FORMAT(`date`, '%m') IN (1,2,3,4,5,6) "; // Jan 1 - Jun 30
			else
				$query .= "AND DATE_FORMAT(`date`, '%m') IN (7,8,9,10,11,12) "; // Jul 1 - Dec 31
		}
		elseif ($filter == 'week')
			$query .= "AND DATE_FORMAT(`date`, '%U') = DATE_FORMAT(NOW(),'%U') ";
		
		if ($this->permission < FULL_ACCESS)
			$query .= "AND (e.tutor1ID = ".$_SESSION['id']." OR e.tutor2ID = ".$_SESSION['id'].")";
		
		$query .= " GROUP BY e.code, e.eventID ORDER BY e.name, date";
		
		return $this->db->getAll($query);
	}

	function getUncompleted ($from = 0, $to = 0, $userID = false) {
		$query = "SELECT e.*, UNIX_TIMESTAMP(e.date) date, TIME_FORMAT(start, '%l:%i %p') start, TIME_FORMAT(finish, '%l:%i %p') finish,
					concat(t1.firstname,' ',t1.lastname) tutorName1, concat(t2.firstname,' ',t2.lastname) tutorName2 , y.type typeName
				  FROM ".TUTORIAL_db.".".$this->_table." e
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID
				  LEFT JOIN ".PORTAL_db.".user t1 ON t1.id = e.tutor1ID
				  LEFT JOIN ".PORTAL_db.".user t2 ON t2.id = e.tutor2ID
				  LEFT JOIN ".TUTORIAL_db.".luType y ON y.typeID = e.typeID
				  WHERE a.studentID IS NULL ";
				  
		if ($from > 0)
			$query .= "AND DATE(e.date) >= DATE('".outputDate("Y-m-d",$from)."') ";
		if ($to > 0)
			$query .= "AND DATE(e.date) <= DATE('".outputDate("Y-m-d",$to)."') ";
		
		if ($userID)
			$query .= "AND (e.tutor1ID = ".$userID." OR e.tutor2ID = ".$userID.") ";
			
		$query .= "GROUP BY e.eventID ORDER BY e.date";
		return $this->db->getAll($query);
	
	}
	
	function getByTutor ($tutorID, $from = 0, $to = 0, $filter = 'both') {
		$query = "SELECT *, UNIX_TIMESTAMP(e.date) date, TIME_FORMAT(start, '%l:%i %p') start, TIME_FORMAT(finish, '%l:%i %p') finish, 
					(TIME_TO_SEC(TIMEDIFF(finish, start))/3600) hours, count(a.studentID) enrolled , UNIX_TIMESTAMP(a.created) marked, y.type typeName
					
				  FROM ".TUTORIAL_db.".".$this->_table." e
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID
				  LEFT JOIN ".TUTORIAL_db.".luType y ON y.typeID = e.typeID
				  WHERE a.studentID IS NOT NULL ";
				 
		if ($from > 0)
			$query .= "AND DATE(a.created) >= DATE('".outputDate("Y-m-d",$from)."') ";
		if ($to > 0)
			$query .= "AND DATE(a.created) <= DATE('".outputDate("Y-m-d",$to)."') ";
		
		if ($filter == 'unprocessed')
			$query .= "AND ((e.tutor1ID = $tutorID AND e.tutor1Processed = 'false') OR (e.tutor2ID = $tutorID AND e.tutor2Processed = 'false')) ";
		elseif ($filter == 'processed')
			$query .= "AND ((e.tutor1ID = $tutorID AND e.tutor1Processed = 'true') OR (e.tutor2ID = $tutorID AND e.tutor2Processed = 'true')) ";
		else
			$query .= "AND (e.tutor1ID = $tutorID OR e.tutor2ID = $tutorID) ";
			
		$query .= "GROUP BY a.eventID ORDER BY e.date";

		return $this->db->getAll($query);				 
	}
	
	function getTotalHoursByTutor($tutorID, $from = 0, $to = 0) {
		$query = "SELECT SUM(TIME_TO_SEC(TIMEDIFF(finish, start))/3600) hours 
					
				  FROM ".TUTORIAL_db.".".$this->_table." e
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID
				  WHERE a.studentID IS NOT NULL AND (e.tutor1ID = $tutorID OR e.tutor2ID = $tutorID) ";
				 
		if ($from > 0)
			$query .= "AND DATE(a.created) >= DATE('".outputDate("Y-m-d",$from)."')";
		if ($to > 0)
			$query .= "AND DATE(a.created) <= DATE('".outputDate("Y-m-d",$to)."')";
			
		$query .= "GROUP BY a.eventID ORDER BY e.date";
		
		return $this->db->getOne($query);		
	}

	function getByStudent ($studentID, $from = 0, $to = 0) {
		$query = "SELECT *, UNIX_TIMESTAMP(e.date) date, TIME_FORMAT(start, '%l:%i %p') start, TIME_FORMAT(finish, '%l:%i %p') finish, 
					(TIME_TO_SEC(SUBTIME(finish, start))/3600) hours, concat(s.firstname,' ',s.lastname) studentName, y.type typeName
				  FROM ".TUTORIAL_db.".".$this->_table." e
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID
				  LEFT JOIN ".PORTAL_db.".user s ON s.id = a.studentID
				  LEFT JOIN ".TUTORIAL_db.".luType y ON y.typeID = e.typeID
				  WHERE a.studentID = $studentID ";
				 
		if ($from > 0)
			$query .= "AND DATE(e.date) >= DATE('".outputDate("Y-m-d",$from)."')";
		if ($to > 0)
			$query .= "AND DATE(e.date) <= DATE('".outputDate("Y-m-d",$to)."')";
			
		$query .= "GROUP BY a.eventID ORDER BY e.typeID DESC, e.code, e.date";
		
		return $this->db->getAll($query);				 
	}

	function getStudentCount($code, $eventID) {
		
		if (!empty($code))
			$query = "SELECT COUNT(*) FROM ".TUTORIAL_db.".enrolment n WHERE n.code = " . $this->db->smartQuote($code);
		else
			$query = "SELECT COUNT(DISTINCT studentID) FROM ".TUTORIAL_db.".attendance a, ".TUTORIAL_db.".event e WHERE a.eventID = $eventID";
			
		return $this->db->getOne($query);
	}
	
	function getEventCount($code, $eventID) {
		$query = "SELECT COUNT(*) FROM ".TUTORIAL_db.".event e WHERE (e.code = " . $this->db->smartQuote($code)." AND TRIM(e.code) != '') OR e.eventID = $eventID";
		return $this->db->getOne($query);
	}
	
	function getByID ($eventID) {
		$query = "SELECT *, UNIX_TIMESTAMP(date) date, TIME_FORMAT(start, '%l:%i %p') start, TIME_FORMAT(finish, '%l:%i %p') finish 
				  FROM ".TUTORIAL_db.".".$this->_table." WHERE ".$this->_index." = $eventID";
		return $this->db->getRow($query);
	}
	
	function getByCode ($code) {
		$query = "SELECT * FROM ".TUTORIAL_db.".".$this->_table." WHERE code = ". $this->db->smartQuote($code) ;
		return $this->db->getRow($query);
	}
	
	function getSequence ($sequenceID) {
		$query = "SELECT *, MIN(UNIX_TIMESTAMP(date)) start_date, MAX(UNIX_TIMESTAMP(date)) end_date, COUNT(*) total 
				  FROM ".TUTORIAL_db.".".$this->_table." 
				  WHERE sequenceID = " . (int)$sequenceID . " 
				  GROUP BY sequenceID";
		return $this->db->getRow($query);
	}
	
	function getNewSequenceID () {
		$query = "SELECT MAX(sequenceID) sequenceID FROM ".TUTORIAL_db.".".$this->_table." ";
		$sequence = $this->db->getRow($query);
		return ($sequence['sequenceID'] + 1);
		
	}
	
	function getTutorList () {
		
		if (Cfg::get('external_enable')) {
			$query = "SELECT ID synergy_id, Title title, Given1 firstname, Surname lastname
					  FROM SynergyOne.dbo.vCommunityAddressesConstit vCommunityAddressesConstit
					  WHERE (vCommunityAddressesConstit.ConstitCode='TUTRES".date('Y')."') OR (vCommunityAddressesConstit.ConstitCode='TUTNONRES".date('Y')."')
					  ORDER BY Surname, Given1";
			require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/mssql.php';
			$mssql = new MSSQL;
			$tutorList = $mssql->getAll($query);
			
		} else {
			$query = "SELECT synergy_id, title, firstname, lastname 
					  FROM user u
					  LEFT JOIN userAccess a ON a.uid = u.id AND a.service = ".TUTORIAL_SERVICE."
					  WHERE a.type >= ".WRITE_ACCESS." ORDER BY lastname, firstname";
			
			$tutorList = $this->db->getAll($query);
		}
		
		$output = array();
		if (is_array($tutorList)) {
			foreach ($tutorList as $tutor) {
				$output[] = array('id'		=> $tutor['synergy_id'],
								  'name'	=> formatName($tutor),
								  );
			}
		}
		return $output;
	}
	
	function getTutorsByEvent ($code = '', $eventID) {
		$query = "SELECT u.* FROM ".TUTORIAL_db.".".$this->_table." e, ".PORTAL_db.".user u 
				  WHERE e.tutor1ID = u.id AND ";
		if (!empty($code))
			$query .= "e.code = " . $this->db->smartQuote($code) . " "; 
		else
			$query .= "e.eventID = " . (int)$eventID . " ";
		
		$query .= "UNION SELECT u.* FROM ".TUTORIAL_db.".".$this->_table." e, ".PORTAL_db.".user u 
				  WHERE e.tutor2ID = u.id AND ";
		if (!empty($code))
			$query .= "e.code = " . $this->db->smartQuote($code) . " "; 
		else
			$query .= "e.eventID = " . (int)$eventID . " ";
							  
		return $this->db->getAll($query);
		
	}
	
	function getClass($classID) {
		$query = "SELECT * FROM ".TUTORIAL_db.".luClass WHERE classID = $classID";
		return $this->db->getRow($query);
	}
	
	function getClassList() {
		$query = "SELECT * FROM ".TUTORIAL_db.".luClass ORDER BY `class`, `code`";
		return $this->db->getAll($query);
	}

	function getTypeList() {
		$query = "SELECT * FROM ".TUTORIAL_db.".luType ORDER BY `typeID`";
		return $this->db->getAll($query);
	}
	
	function process($eventID, $tutorID, $processed = true) {
		$event = $this->getByID($eventID);
		$data = array('eventID' => $event['eventID']);

		if ($event['tutor1ID'] == $tutorID)
			$data['tutor1Processed'] = $processed ? 'true' : 'false';
		elseif ($event['tutor2ID'] == $tutorID)
			$data['tutor2Processed'] = $processed ? 'true' : 'false';

		$this->update($data);
	}
	
	function deleteBySequence($sequenceID) {
		$query = "SELECT * FROM ".TUTORIAL_db.".event WHERE sequenceID = " . (int)$sequenceID;
		$eventList = $this->db->getAll($query);
		if (is_array($eventList)) {
			foreach ($eventList as $event) {
				$this->delete($event['eventID']);
			}
		}
	}

	function create($data) {
		
		$data['created'] = 'NOW()';
		$data['modified'] = 'NOW()';
		$this->db->changeDb(TUTORIAL_db);
		$eventID = parent::create($data);
		$this->db->changeDb(PORTAL_db);
		
		return $eventID;
	}

	function update($data) {
		
		$data['modified'] = 'NOW()';
		$this->db->changeDb(TUTORIAL_db);
		parent::update($data);
		$this->db->changeDb(PORTAL_db);
	}

	function delete($eventID) {
		
		require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/attendance.php';
		$attendanceHandler = new Attendance;
		$attendanceHandler->deleteByEvent($eventID);
		
		$this->db->changeDb(TUTORIAL_db);		
		parent::delete($eventID);
		$this->db->changeDb(PORTAL_db);		
	}
	
}
?>