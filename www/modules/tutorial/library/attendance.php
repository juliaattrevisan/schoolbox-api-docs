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
	
	function getAllByEvent($eventID) {
		$query = "SELECT e.eventID, n.studentID, a.present, a.reason, CONCAT(u.lastname,', ',u.firstname) studentName 
				  FROM ".TUTORIAL_db.".event e
				  LEFT JOIN ".TUTORIAL_db.".enrolment n ON ((n.code = e.code AND n.code != '') OR n.eventID = e.eventID) AND n.from <= e.date 
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID AND a.studentID = n.studentID
				  LEFT JOIN ".PORTAL_db.".user u ON u.id = n.studentID			  
				  WHERE e.eventID = $eventID 
				  AND n.studentID IS NOT NULL 
				  
				  ORDER BY u.lastname, u.firstname ";
				
		return $this->db->getAll($query);	
	}
	
	function getByStudent($studentID, $code) {
		$query = "SELECT count(*) present,
				  (SELECT count(*) FROM ".TUTORIAL_db.".attendance a, ".TUTORIAL_db.".event e WHERE a.studentID = $studentID AND a.eventID = e.eventID AND e.code = ".$this->db->smartQuote($code).") expected
				  FROM ".TUTORIAL_db.".attendance a, ".TUTORIAL_db.".event e WHERE a.studentID = $studentID AND a.eventID = e.eventID AND a.present = 'true' AND e.code = " . $this->db->smartQuote($code);
		return $this->db->getRow($query);
	}
	
	function getReasonList () {
		$query = "SELECT * FROM ".TUTORIAL_db.".luReason";
		return $this->db->getAll($query);
	}

	function deleteByEvent($eventID) {
		$query = "DELETE FROM ".TUTORIAL_db.".".$this->_table." WHERE eventID = $eventID";
		return $this->db->query($query);
	}

	function save($data) {
		$current = $this->getByStudent($data['studentID'], $data['code']);
		if ($current['attendanceID'])
			return $this->update($data);
		else
			return $this->create($data);
	}

	function update($data) {
		$this->db->changeDb(TUTORIAL_db);
		$result = parent::create($data);
		$this->db->changeDb(PORTAL_db);
		return $result;	
	}

	function create($data) {
		
		$data['created'] = 'NOW()';
		$this->db->changeDb(TUTORIAL_db);
		$result = parent::create($data);
		$this->db->changeDb(PORTAL_db);
		return $result;
	}	

	
}
?>