<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';
define('TUTORIAL_db', 'tutorial');
define('PORTAL_db', SQL_db);

class Enrolment extends Model {
	function Enrolment() {
		$this->_table = "enrolment";
		$this->_index = "enrolmentID";
		
		parent::Model();
	}
	
	function getAllByEvent($code, $eventID) {
		if (!empty($code)) {
			$query = "SELECT n.studentID FROM ".TUTORIAL_db.".enrolment n WHERE n.code = ".$this->db->smartQuote($code)."
					  UNION SELECT a.studentID FROM ".TUTORIAL_db.".attendance a, ".TUTORIAL_db.".event e WHERE a.eventID = e.eventID AND e.code = ".$this->db->smartQuote($code)."";
		} else {
			$query = "SELECT n.studentID FROM ".TUTORIAL_db.".enrolment n WHERE n.eventID = " . $eventID . "
					  UNION SELECT a.studentID FROM ".TUTORIAL_db.".attendance a WHERE a.eventID = " . $eventID;
		}
		
		return $this->db->getAll($query);	
	}
	
	function isEnrolled($studentID, $code, $eventID) {
		if (!empty($code))
			$query = "SELECT * FROM ".TUTORIAL_db.".enrolment n WHERE n.studentID = " . $studentID . " AND n.code = ".$this->db->smartQuote($code)."";
		else
			$query = "SELECT * FROM ".TUTORIAL_db.".enrolment n WHERE n.studentID = " . $studentID . " AND n.eventID = ".$eventID."";
		$result = $this->db->getRow($query);
		if (isset($result['studentID']))
			return true;
		else
			return false;
	}
	
	function deleteByStudent($studentID, $code, $eventID = 0) {
		$query = "DELETE FROM ".TUTORIAL_db.".enrolment WHERE ";
		if (!empty($code))
			$query .= "code = ".$this->db->smartQuote($code)." "; 
		elseif (is_numeric($eventID))
			$query .= "eventID = $eventID ";	
		
		$query .= "AND studentID = $studentID";
		return $this->db->query($query);
	}	
	
	function create($data) {
	
		require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';
		$eventHandler = new Event;
		$event = $eventHandler->getByID($data['eventID']);
		$data['from'] = outputDate("Y-m-d",$event['date']);
	
		$this->deleteByStudent($data['studentID'], $data['code'], $data['eventID']);
		
		$this->db->changeDb(TUTORIAL_db);
		$result = parent::create($data);
		$this->db->changeDb(PORTAL_db);
		
		return $result;
	}	
	
}
?>