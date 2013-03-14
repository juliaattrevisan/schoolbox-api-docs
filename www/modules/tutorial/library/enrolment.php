<?php

define('TUTORIAL_db', 'tutorial');
define('PORTAL_db', SQL_db);

class Enrolment extends DeprecatedModel {
	function Enrolment() {
		$this->_table = "enrolment";
		$this->_index = "enrolmentID";

		parent::DeprecatedModel();
	}

	function getAllByEvent($code, $eventID) {
		if (!empty($code)) {
			$query = "(SELECT n.studentID, u.lastname, u.firstname, u.title FROM ".TUTORIAL_db.".enrolment n LEFT JOIN ".PORTAL_db.".user u ON u.id = n.studentID WHERE n.code = ".$this->db->smartQuote($code)." )
					  UNION (SELECT a.studentID, s.lastname, s.firstname, s.title FROM (".TUTORIAL_db.".attendance a, ".TUTORIAL_db.".event e) LEFT JOIN ".PORTAL_db.".user s ON s.id = a.studentID WHERE a.eventID = e.eventID AND e.code = ".$this->db->smartQuote($code).")
					  ORDER BY lastname, firstname";
		} else {
			$query = "(SELECT n.studentID, u.lastname, u.firstname, u.title FROM ".TUTORIAL_db.".enrolment n LEFT JOIN ".PORTAL_db.".user u ON u.id = n.studentID WHERE n.eventID = " . $eventID . " )
					  UNION ( SELECT a.studentID, s.lastname, s.firstname, s.title FROM ".TUTORIAL_db.".attendance a LEFT JOIN ".PORTAL_db.".user s ON s.id = a.studentID WHERE a.eventID = " . $eventID . " )
					  ORDER BY lastname, firstname";
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

		$date = new Alaress_Date_Format($event['startDate'], 'y-m-d h:i:s');

		$data['from'] = $date->getDatabaseDate();

		$this->deleteByStudent($data['studentID'], $data['code'], $data['eventID']);

		$this->db->changeDb(TUTORIAL_db);
		$result = parent::create($data);
		$this->db->changeDb(PORTAL_db);

		return $result;
	}

}
?>