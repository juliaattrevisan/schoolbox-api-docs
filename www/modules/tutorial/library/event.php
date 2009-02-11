<?php

require_once SITE_root.'/include/model.php';
require_once SITE_root.'/library/group.php';
define('TUTORIAL_db', 'tutorial');
define('PORTAL_db', SQL_db);

class Event extends Model {
	function Event() {
		$this->_table = "event";
		$this->_index = "eventID";
		
		parent::Model();
	}
	
	
	function getAll($filter = '') {
		$query = "SELECT e.*, UNIX_TIMESTAMP(e.date) date, TIME_FORMAT(start, '%l:%i %p') start, TIME_FORMAT(finish, '%l:%i %p') finish, 
					concat(t1.firstname,' ',t1.lastname) tutorName1, concat(t2.firstname,' ',t2.lastname) tutorName2 
				  FROM ".TUTORIAL_db.".".$this->_table." e
				  LEFT JOIN ".TUTORIAL_db.".enrolment n ON (n.code = e.code AND n.code != '') OR n.eventID = e.eventID
				  LEFT JOIN ".PORTAL_db.".user t1 ON t1.id = e.tutorID_1
				  LEFT JOIN ".PORTAL_db.".user t2 ON t2.id = e.tutorID_2 
				  WHERE 1=1 ";
				  
		if ($filter == 'upcoming')
			$query .= "AND UNIX_TIMESTAMP(date) > ".strtotime("12am today")." ";
		elseif ($filter == 'semester')
			$query .= "AND DATE_FORMAT(`date`, '%m') IN (".($month = date('m')).",".($month+1).",".($month-1).") ";
		elseif ($filter != 'all')
			$query .= "AND DATE_FORMAT(`date`, '%U') = DATE_FORMAT(NOW(),'%U') ";
		
		
		if (!$_SESSION['su'])
			$query .= "AND (e.tutorID_1 = ".$_SESSION['id']." OR e.tutorID_2 = ".$_SESSION['id']." OR n.studentID = " . $_SESSION['id'].") ";
			
		$query .= "GROUP BY eventID ORDER BY e.date, e.start";
		
		return $this->db->getAll($query);
	}
	
	function getWeek($week) {
		$query = "SELECT e.*, UNIX_TIMESTAMP(e.date) date, TIME_FORMAT(start, '%l:%i %p') start, TIME_FORMAT(finish, '%l:%i %p') finish, concat(t1.firstname,' ',t1.lastname) tutorName1, concat(t2.firstname,' ',t2.lastname) tutorName2 FROM ".TUTORIAL_db.".".$this->_table." e
				  LEFT JOIN ".TUTORIAL_db.".enrolment n ON n.code = e.code 
				  LEFT JOIN ".PORTAL_db.".user t1 ON t1.id = e.tutorID_1
				  LEFT JOIN ".PORTAL_db.".user t2 ON t2.id = e.tutorID_2 
				  WHERE DATE_FORMAT(`date`, '%U') = $week ";

		if (isStaff($_SESSION['gid']))
			$query .= "AND (e.tutorID_1 = ".$_SESSION['id']." OR e.tutorID_2 = ".$_SESSION['id'].") ";
		elseif (!$_SESSION['su'])
			$query .= "AND n.studentID = " . $_SESSION['id']." ";
		
		$query .= "GROUP BY eventID ORDER BY date ASC";
		
		return $this->db->getAll($query);			
	}
	
	function getStatistics ($filter = '') {
		$query = "SELECT DISTINCT e.*, UNIX_TIMESTAMP(e.date) date, TIME_FORMAT(start, '%l:%i %p') start, TIME_FORMAT(finish, '%l:%i %p') finish, 
				  concat(t1.firstname,' ',t1.lastname) tutorName1, concat(t2.firstname,' ',t2.lastname) tutorName2,
				  (SELECT count(*) FROM ".TUTORIAL_db.".attendance a2, ".TUTORIAL_db.".event e2 WHERE a2.eventID = e2.eventID AND e2.code = e.code AND a2.present = 'true') present,
				  (SELECT count(*) FROM ".TUTORIAL_db.".attendance a3, ".TUTORIAL_db.".event e3 WHERE a3.eventID = e3.eventID AND e3.code = e.code) expected
				  FROM ".TUTORIAL_db.".".$this->_table." e
				  LEFT JOIN ".PORTAL_db.".user t1 ON t1.id = e.tutorID_1
				  LEFT JOIN ".PORTAL_db.".user t2 ON t2.id = e.tutorID_2 
				  WHERE 1=1 ";
				  
		if ($filter == 'upcoming')
			$query .= "AND UNIX_TIMESTAMP(date) > ".strtotime("12am today")." ";
		elseif ($filter == 'semester')
			$query .= "AND DATE_FORMAT(`date`, '%m') IN (".($month = date('m')).",".($month+1).",".($month-1).") ";
		elseif ($filter == 'week')
			$query .= "AND DATE_FORMAT(`date`, '%U') = DATE_FORMAT(NOW(),'%U') ";
		
		$query .= "ORDER BY e.code, date";

		return $this->db->getAll($query);
	}

	function getUncompleted ($from = 0, $to = 0) {
		$query = "SELECT e.*, UNIX_TIMESTAMP(e.date) date, concat(t1.firstname,' ',t1.lastname) tutorName1, concat(t2.firstname,' ',t2.lastname) tutorName2 
				  FROM ".TUTORIAL_db.".".$this->_table." e
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID
				  LEFT JOIN ".PORTAL_db.".user t1 ON t1.id = e.tutorID_1
				  LEFT JOIN ".PORTAL_db.".user t2 ON t2.id = e.tutorID_2
				  WHERE a.studentID IS NULL ";
				  
		if ($from > 0)
			$query .= "AND DATE(e.date) >= DATE('".outputDate("Y-m-d",$from)."')";
		if ($to > 0)
			$query .= "AND DATE(e.date) <= DATE('".outputDate("Y-m-d",$to)."')";
			
		$query .= "GROUP BY e.eventID ORDER BY e.date";
		return $this->db->getAll($query);
	
	}
	
	function getByTutor ($tutorID, $from = 0, $to = 0) {
		$query = "SELECT *, UNIX_TIMESTAMP(e.date) date, TIME_FORMAT(start, '%l:%i %p') start, TIME_FORMAT(finish, '%l:%i %p') finish, 
					(TIME_TO_SEC(SUBTIME(finish, start))/3600) hours, count(a.studentID) students , UNIX_TIMESTAMP(a.created) marked
				  FROM ".TUTORIAL_db.".".$this->_table." e
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID
				  WHERE a.studentID IS NOT NULL AND (e.tutorID_1 = $tutorID OR e.tutorID_2 = $tutorID) ";
				 
		if ($from > 0)
			$query .= "AND DATE(a.created) >= DATE('".outputDate("Y-m-d",$from)."')";
		if ($to > 0)
			$query .= "AND DATE(a.created) <= DATE('".outputDate("Y-m-d",$to)."')";
			
		$query .= "GROUP BY a.eventID ORDER BY e.date";

		return $this->db->getAll($query);				 
	}

	function getByStudent ($studentID, $from = 0, $to = 0) {
		$query = "SELECT *, UNIX_TIMESTAMP(e.date) date, TIME_FORMAT(start, '%l:%i %p') start, TIME_FORMAT(finish, '%l:%i %p') finish, 
					(TIME_TO_SEC(SUBTIME(finish, start))/3600) hours, concat(s.firstname,' ',s.lastname) studentName
				  FROM ".TUTORIAL_db.".".$this->_table." e
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID
				  LEFT JOIN ".PORTAL_db.".user s ON s.id = a.studentID
				  WHERE a.studentID = $studentID ";
				 
		if ($from > 0)
			$query .= "AND DATE(e.date) >= DATE('".outputDate("Y-m-d",$from)."')";
		if ($to > 0)
			$query .= "AND DATE(e.date) <= DATE('".outputDate("Y-m-d",$to)."')";
			
		$query .= "GROUP BY a.eventID ORDER BY e.code, e.date";
		
		return $this->db->getAll($query);				 
	}

	function getStudentCount($code, $eventID) {
		$query = "SELECT studentID FROM ".TUTORIAL_db.".attendance a, ".TUTORIAL_db.".event e 
				  WHERE (a.eventID = e.eventID AND e.code = ".$this->db->smartQuote($code)." AND TRIM(e.code) != '' ) OR a.eventID = $eventID GROUP BY studentID
				  UNION SELECT studentID FROM ".TUTORIAL_db.".enrolment n 
				  WHERE (n.code = " . $this->db->smartQuote($code)." AND TRIM(n.code) != '' ) OR n.eventID = $eventID";
				
		$result = $this->db->getAll($query);
		return $this->db->affectedRows($result);		
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
	
	function getTutorList () {
		$query = "SELECT id, title, firstname, lastname 
				  FROM user u
				  LEFT JOIN userAccess a ON a.uid = u.id AND a.service = ".TUTORIAL_SERVICE."
				  WHERE a.type >= ".WRITE_ACCESS." ORDER BY lastname, firstname";
		
		$tutorList = $this->db->getAll($query);
		$output = array();
		if (is_array($tutorList)) {
			foreach ($tutorList as $tutor) {
				$output[] = array('id'		=> $tutor['id'],
								  'name'	=> formatName($tutor),
								  );
			}
		}
		return $output;
	}
	
	function getTutorsByEvent ($code) {
		$query = "SELECT CONCAT(u.firstname,' ',u.lastname) name FROM ".TUTORIAL_db.".".$this->_table." e, ".PORTAL_db.".user u 
				  WHERE e.tutorID_1 = u.id AND e.code = " . $this->db->smartQuote($code) . " 
				  UNION SELECT CONCAT(u.firstname,' ',u.lastname) FROM ".TUTORIAL_db.".".$this->_table." e, ".PORTAL_db.".user u 
				  WHERE e.tutorID_2 = u.id AND e.code = " . $this->db->smartQuote($code);
		$tutorList =  $this->db->getAll($query);
		$output = array();
		if (is_array($tutorList)) {
			foreach ($tutorList as $tutor) {
				$output[] = $tutor['name'];
			}
		}
		return $output;
	}

	function create($data) {
		if (!$_SESSION['su'] && $data['tutorID_1'] != $_SESSION['id'] && $data['tutorID_2'] != $_SESSION['id'])
			error(8);	
		$data['created'] = 'NOW()';
		$data['modified'] = 'NOW()';
		$this->db->changeDb(TUTORIAL_db);
		$eventID = parent::create($data);
		$this->db->changeDb(PORTAL_db);
		
		return $eventID;
	}

	function update($data) {
		
		$current = $this->getByID($data['eventID']);
		if (!$_SESSION['su'] && $current['tutorID_1'] != $_SESSION['id'] && $current['tutorID_2'] != $_SESSION['id'])
			error(8);
		
		$data['modified'] = 'NOW()';
		$this->db->changeDb(TUTORIAL_db);
		parent::update($data);
		$this->db->changeDb(PORTAL_db);
	}

	function delete($eventID) {
		$current = $this->getByID($eventID);
		if (!$_SESSION['su'] && $current['tutorID_1'] != $_SESSION['id'] && $current['tutorID_2'] != $_SESSION['id'])
			error(8);

		require_once SITE_root . '/www/modules/tutorial/library/attendance.php';
		$attendanceHandler = new Attendance;
		$attendanceHandler->deleteByEvent($eventID);
		
		$this->db->changeDb(TUTORIAL_db);		
		parent::delete($eventID);
		$this->db->changeDb(PORTAL_db);		
	}
	
}
?>