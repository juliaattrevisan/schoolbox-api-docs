<?php

require_once SITE_root.'/include/model.php';
define('TUTORIAL_db', 'tutorial');
define('PORTAL_db', SQL_db);

class Attendance extends Model {
	function Attendance() {
		$this->_table = "attendance";
		$this->_index = "attendanceID";
		
		parent::Model();
	}
	
	function getAttendanceByEvent ($eventID) {
		$query = "SELECT e.eventID, n.studentID, a.present, a.reason, CONCAT(u.lastname,', ',u.firstname) studentName 
				  FROM ".TUTORIAL_db.".event e
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID
				  LEFT JOIN ".PORTAL_db.".user u ON u.id = a.studentID				  
				  WHERE e.eventID = $eventID ORDER BY u.lastname, u.firstname";
		return $this->db->getAll($query);
	}

	function getStudentAttendance ($code, $studentID) {
		$query = "SELECT count(*) present,
				  (SELECT count(*) FROM ".TUTORIAL_db.".attendance a, ".TUTORIAL_db.".event e WHERE a.studentID = $studentID AND a.eventID = e.eventID AND e.code = ".$this->db->smartQuote($code).") expected
				  FROM ".TUTORIAL_db.".attendance a, ".TUTORIAL_db.".event e WHERE a.studentID = $studentID AND a.eventID = e.eventID AND a.present = 'true' AND e.code = " . $this->db->smartQuote($code);
		$attendance = $this->db->getRow($query);
		if ($attendance['expected'] > 0)
			return number_format(($attendance['present']/$attendance['expected'])*100, 2);
		else
			return 'N/A';
	}
	
	function getAllByEvent($eventID) {
		$query = "SELECT e.eventID, n.studentID, a.present, a.reason, CONCAT(u.lastname,', ',u.firstname) studentName 
				  FROM ".TUTORIAL_db.".event e
				  LEFT JOIN ".TUTORIAL_db.".enrolment n ON n.code = e.code
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID AND a.studentID = n.studentID
				  LEFT JOIN ".PORTAL_db.".user u ON u.id = n.studentID			  
				  WHERE e.eventID = $eventID ORDER BY u.lastname, u.firstname ";
		return $this->db->getAll($query);	
	}
	
	function setAttendance ($eventID, $studentID, $present = false, $reason = '') {
		$query = "INSERT INTO ".TUTORIAL_db.".".$this->_table." (eventID, studentID, present, reason) 
				  VALUES ($eventID, $studentID, '".($present?'true':'false')."', ".$this->db->smartQuote($reason).") 
				  ON DUPLICATE KEY UPDATE present=VALUES(present), reason=VALUES(reason)";
		return $this->db->query($query);
	}	
	
	function setReason ($eventID, $studentID, $reason = '') {
		$query = "INSERT INTO ".TUTORIAL_db.".".$this->_table." (eventID, studentID, reason) VALUES ($eventID, $studentID, ".$this->db->smartQuote($reason).") 
				  ON DUPLICATE KEY UPDATE reason=VALUES(reason)";
		return $this->db->query($query);
	}

	function setPresent ($eventID, $studentID, $present = false) {
		$query = "INSERT INTO ".TUTORIAL_db.".".$this->_table." (eventID, studentID, present) VALUES ($eventID, $studentID, '".($present?'true':'false')."') 
				  ON DUPLICATE KEY UPDATE present=VALUES(present)";
		return $this->db->query($query);
	}
	
	function deleteEnrolment ($eventID, $studentID) {
		require_once dirname(__FILE__) . '/event.php';
		$eventHandler = new Event;
		$event = $eventHandler->getByID($eventID);
		$query = "DELETE FROM ".TUTORIAL_db.".enrolment WHERE code = ".$this->db->smartQuote($event['code'])." AND studentID = $studentID";
		return $this->db->query($query);
	}	
	
	function getReasonList () {
		$query = "SELECT * FROM ".TUTORIAL_db.".luReason";
		return $this->db->getAll($query);
	}
	
}
?>