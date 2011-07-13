<?php
require_once SITE_root . '/include/model.php';

class Workshop extends DeprecatedModel {

	function __construct () {
	
		$this->_table = 'workshop';
		$this->_index = 'workshopID';
		
		$this->_bookingTable = 'workshopBooking';
		$this->_sessionTable = 'workshopSession';
		$this->_roomTable = 'workshopRoom';
		
		parent::__construct();
	}
	
	function getByID($workshopID) {
		$query = "SELECT w.*, COUNT(b.userID) booked, s.start, s.end FROM ".$this->_table." w
				  LEFT JOIN ".$this->_bookingTable." b ON b.workshopID = w.workshopID
				  LEFT JOIN ".$this->_sessionTable." s ON s.sessionID = w.sessionID
				  WHERE w.workshopID = $workshopID
				  GROUP BY w.workshopID ";
		return $this->db->getRow($query);
	}
	
	function getAll($order = '') {
		$query = "SELECT w.*, COUNT(b.userID) booked FROM ".$this->_table." w 
				  LEFT JOIN ".$this->_bookingTable." b ON b.workshopID = w.workshopID
				  GROUP BY w.workshopID ";
				  
		if (!empty($order))
			$query .= "ORDER BY $order";
		
		return $this->db->getAll($query);
	}
	
	function getSessionList() {
		$query = "SELECT * FROM ".$this->_sessionTable." ORDER BY sessionID";
		return $this->db->getAll($query);
	}
	
	function getRoomList() {
		$query = "SELECT DISTINCT room FROM ".$this->_table." ORDER BY room";
		return $this->db->getAll($query);
	}
	
	function getBySession($sessionID) {
		$query = "SELECT w.*, COUNT(b.userID) booked FROM ".$this->_table." w
				  LEFT JOIN ".$this->_bookingTable." b ON b.workshopID = w.workshopID
				  WHERE sessionID = $sessionID 
				  GROUP BY w.workshopID
				  ORDER BY room ";
		return $this->db->getAll($query);
	}
	
	function getByRoom($room) {
		$query = "SELECT w.*, COUNT(b.userID) booked FROM ".$this->_table." w
				  LEFT JOIN ".$this->_bookingTable." b ON b.workshopID = w.workshopID
				  WHERE room = ".$this->db->smartQuote($room )."
				  GROUP BY w.workshopID
				  ORDER BY room ";
		return $this->db->getAll($query);	
	}
	
	function getBookingList($userID = 0) {
		if (!$userID)
			$userID = $_SESSION['id'];
			
		$query = "SELECT * FROM ".$this->_bookingTable." b
				  LEFT JOIN ".$this->_table." w ON w.workshopID = b.workshopID
				  LEFT JOIN ".$this->_sessionTable." s ON s.sessionID = w.sessionID
				  WHERE b.userID = $userID
				  ORDER BY w.sessionID
				  ";
		return $this->db->getAll($query);
	}
	
	function placeBooking($workshopID, $userID = 0) {
		if (!$userID)
			$userID = $_SESSION['id'];
		
		$booking = array('userID'		=> $userID,
						 'workshopID'	=> $workshopID,
						 );
						 
		return $this->db->insert($this->_bookingTable, $booking);	
	}
	
	function deleteBooking($workshopID, $userID = 0) {
		if (!$userID)
			$userID = $_SESSION['id'];
		
		$query = "DELETE FROM ".$this->_bookingTable." WHERE workshopID = $workshopID AND userID = $userID";
		return $this->db->query($query);	
	}	
	
	function sessionOverlap($sessionID, $length, $booked) {
		for ($i = $sessionID; $i < $sessionID + $length; $i++) {
			if (isset($booked[$i]))
				return true;
		}
		return false;
	}
	
	function getUserList($workshopID) {
		$query = "SELECT u.*, c.name campusName FROM `user` u
				  JOIN ".$this->_bookingTable." b ON b.userID = u.id
				  LEFT JOIN luCampus c ON c.id = u.campus
				  WHERE b.workshopID = $workshopID 
				  ORDER BY u.lastname, u.firstname ";
				  
		return $this->db->getAll($query);
	}

	function getAllUsers() {
		$query = "SELECT DISTINCT u.*, c.name campusName
				  FROM `user` u
				  LEFT JOIN luCampus c ON c.id = u.campus
				  JOIN ".$this->_bookingTable." b ON b.userID = u.id
				  ORDER BY u.lastname, u.firstname ";
				  
		return $this->db->getAll($query);
	}
	
	function getByUser($userID) {
		$query = "SELECT w.* FROM ".$this->_table." w, ".$this->_bookingTable." b 
				  WHERE b.workshopID = w.workshopID AND b.userID = $userID
				  ";
		return $this->db->getAll($query);
	}
	
}

?>