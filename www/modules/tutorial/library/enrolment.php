<?php

require_once SITE_root.'/include/model.php';
define('TUTORIAL_db', 'tutorial');
define('PORTAL_db', SQL_db);

class Enrolment extends Model {
	function Enrolment() {
		$this->_table = "attendance";
		$this->_index = "attendanceID";
		
		parent::Model();
	}
	
	function getAllByCode($code) {
		$query = "SELECT n.*, CONCAT(u.lastname,', ',u.firstname) studentName 
				  FROM ".TUTORIAL_db.".enrolment n
				  LEFT JOIN ".PORTAL_db.".user u ON u.id = n.studentID			  
				  WHERE n.code = ".$this->db->smartQuote($code)."  ORDER BY u.lastname, u.firstname";
		return $this->db->getAll($query);	
	}
	
	function delete($code, $studentID) {
		$query = "DELETE FROM ".TUTORIAL_db.".enrolment WHERE code = ".$this->db->smartQuote($code)." AND studentID = $studentID";
		return $this->db->query($query);
	}	
	
	function create($code, $studentID) {
		$query = "REPLACE INTO ".TUTORIAL_db.".enrolment SET code = ".$this->db->smartQuote($code).", studentID = $studentID";
		return $this->db->query($query);
	}	
	
}
?>