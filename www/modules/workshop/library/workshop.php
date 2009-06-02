<?php
require_once SITE_root . '/include/model.php';

class Workshop extends Model {

	function __construct () {
	
		$this->_table = 'workshop';
		$this->_index = 'workshopID';
		
		$this->_bookingTable = 'workshopBooking';
		$this->_sessionTable = 'workshopSession';
		$this->_roomTable = 'workshopRoom';
		
		parent::__construct();
	}
	
	function getByID($workshopID) {
		$query = "SELECT w.*, COUNT(b.userID) booked FROM ".$this->_table." w
				  LEFT JOIN ".$this->_bookingTable." b ON b.workshopID = w.workshopID
				  WHERE w.workshopID = $workshopID
				  GROUP BY w.workshopID ";
		return $this->db->getRow($query);
	}
	
	function getSessionList() {
		$query = "SELECT * FROM ".$this->_sessionTable." ORDER BY sessionID";
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
	
	function getBookingList($userID = 0) {
		if (!$userID)
			$userID = $_SESSION['id'];
			
		$query = "SELECT * FROM ".$this->_bookingTable." b
				  LEFT JOIN ".$this->_table." w ON w.workshopID = b.workshopID
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
}

?>