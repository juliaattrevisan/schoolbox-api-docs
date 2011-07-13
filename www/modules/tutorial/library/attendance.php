<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';
define('TUTORIAL_db', 'tutorial');
define('PORTAL_db', SQL_db);

class Attendance extends DeprecatedModel {
	function Attendance() {
		$this->_table = "attendance";
		$this->_index = "attendanceID";
		
		parent::DeprecatedModel();
	}
	
	function getAllByEvent($eventID) {
		
		// who might have attended
		$query = "(SELECT e.eventID, n.studentID, a.present, a.reason, u.lastname ,u.firstname, u.title
				  FROM ".TUTORIAL_db.".event e
				  LEFT JOIN ".TUTORIAL_db.".enrolment n ON ((n.code = e.code AND n.code != '' AND e.typeID = 1) OR n.eventID = e.eventID) AND n.from <= e.date 
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID AND a.studentID = n.studentID
				  LEFT JOIN ".PORTAL_db.".user u ON u.id = n.studentID			  
				  WHERE e.eventID = $eventID 
				  AND n.studentID IS NOT NULL )
				  
				  UNION";
		
		// and who did attend
		$query .= "(SELECT e.eventID, a.studentID, a.present, a.reason, u.lastname ,u.firstname, u.title
				  FROM ".TUTORIAL_db.".event e
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID
				  LEFT JOIN ".PORTAL_db.".user u ON u.id = a.studentID			  
				  WHERE e.eventID = $eventID AND a.studentID IS NOT NULL) 
				  ORDER BY lastname, firstname ";
		
		return $this->db->getAll($query);
		
	}
	
	function getAllByStudent($studentID, $code, $eventID) {
		$query = "SELECT *, UNIX_TIMESTAMP(e.date) date FROM ".TUTORIAL_db.".event e 
				  LEFT JOIN ".TUTORIAL_db.".attendance a ON a.eventID = e.eventID AND a.studentID = $studentID
				  WHERE ";
		if (!empty($code))
			$query .= "e.code = ". $this->db->smartQuote($code)." ";	
		else
			$query .= "e.eventID = $eventID "; 
		
		$query .= "ORDER BY e.date ASC";

		return $this->db->getAll($query);			  
	}
	
	function getByStudent($studentID, $code) {
		$query = "SELECT count(*) present, (SELECT count(*)
                          FROM ".TUTORIAL_db.".attendance a, ".TUTORIAL_db.".event e
                          WHERE a.studentID = $studentID
                              AND a.eventID = e.eventID
                              AND e.code = ".$this->db->smartQuote($code).") expected
				  FROM ".TUTORIAL_db.".attendance a, ".TUTORIAL_db.".event e
				  WHERE a.studentID = $studentID
					  AND a.eventID = e.eventID
					  AND a.present = 'true'
					  AND e.code = ".$this->db->smartQuote($code);
					  
		return $this->db->getRow($query);
	}
	
	function getCountByEvent($eventID) {
		$query = "SELECT COUNT(*) FROM ".TUTORIAL_db.".attendance a WHERE a.eventID = $eventID AND a.present = 'true'";
		return $this->db->getOne($query);
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