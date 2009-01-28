<?php

require_once SITE_root.'/include/model.php';
define('TUTORIAL_db', 'tutorial');
define('PORTAL_db', SQL_db);

class Enrolment extends Model {
	function Enrolment() {
		$this->_table = "enrolment";
		$this->_index = "enrolmentID";
		
		parent::Model();
	}
	
	function getAllByEvent($code, $eventID) {
		$query = "SELECT n.*, CONCAT(u.lastname,', ',u.firstname) studentName 
				  FROM ".TUTORIAL_db.".enrolment n
				  LEFT JOIN ".PORTAL_db.".user u ON u.id = n.studentID			  
				  WHERE (n.code = ".$this->db->smartQuote($code)." AND TRIM(n.code) != '') OR n.eventID = $eventID  ORDER BY u.lastname, u.firstname";
		return $this->db->getAll($query);	
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
	
		require_once SITE_root . '/www/modules/tutorial/library/event.php';
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