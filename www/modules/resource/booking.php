<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
************************************************************/

/* Class defines interface between resource booking database and software.         *
 * The following actions can be performed; delete, add, update, retrieve */

require_once('conf.php');

class booking
{
	function booking()
        {
                //always gonna need the db so lets create one..
                $this->db = new dbManager();
                $this->db->dbConnect();
        }

		//checks if resources has been booked between the times specified
	function isBooked($resource, $start, $end, $exclude=0)
	{
		$query = "SELECT id FROM resourceBooking 
				  WHERE rid = '$resource' AND ( 
					 ( FROM_UNIXTIME('$start') > start AND FROM_UNIXTIME('$start') < end  ) 
					  OR
					 ( FROM_UNIXTIME('$end') > start AND FROM_UNIXTIME('$end') < end  ) 
					)";
		
		if($exclude)
			$query .= " AND id != '$exclude'";
		
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
			return true;
		else
			return false;
				
	}
	
	function updateBooking($id, $start, $end)
	{
		$query = "UPDATE resourceBooking SET start = FROM_UNIXTIME('$start'), end = FROM_UNIXTIME('$end') WHERE id = '$id'";
		return $this->db->dbDoQuery($query);
	}
	
	function requestBooking($resource, $requestor, $start, $end)
	{
		$query = "INSERT INTO resourceBooking (rid, created, creator, start, end, type) VALUES
				  ('$resource', NOW(), '$requestor', FROM_UNIXTIME('$start'), FROM_UNIXTIME('$end'), 'request')";
				  
		return $this->db->dbDoQuery($query);
	}
	
	function addBooking($resource, $creator, $start, $end)
	{
		$query = "INSERT INTO resourceBooking (rid, created, creator, start, end, type) VALUES
				  ('$resource', NOW(), '$creator', FROM_UNIXTIME('$start'), FROM_UNIXTIME('$end'), 'locked')";
				  
		return $this->db->dbDoQuery($query);
	}
	
	function deleteBooking($id)
	{
		$query = "DELETE FROM resourceBooking WHERE id = '$id'";
		return $this->db->dbDoQuery($query);
	}
	
	function authoriseBooking($id)
	{
		$query = "UPDATE resourceBooking SET type = 'locked' WHERE id = '$id'";
		return $this->db->dbDoQuery($query);
	}
	
	function denyBooking($id)
	{
		$query = "DELETE FROM resourceBooking WHERE id = '$id'";
		return $this->db->dbDoQuery($query);
	}
	
	function getBookings($resource, $start, $end)
	{
		$query = "SELECT resourceBooking.id, created, start, end, user.id as userid, firstname, lastname, type FROM resourceBooking JOIN user ON creator = user.id
					WHERE rid = '$resource' AND start >= FROM_UNIXTIME('$start') AND end <= FROM_UNIXTIME('$end')";
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
		{
			while($bookings[] = $this->db->dbGetNextArray());
			array_pop($bookings);
		}
		return $bookings;
	}
	
	function getBooking($id)
	{
		$query = "SELECT * FROM resourceBooking
				WHERE id = '$id'";
		
		$this->db->dbDoQuery($query);
		return $this->db->dbGetNextArray();
	}
	
	function checkTimeRange($rid, $start, $end)
	{
		//FIXME: implment resource based time ranges
		if((date('G',$start) >= START_TIME && date('G',$start) <= END_TIME) && (date('G',$end) >= START_TIME && date('G',$end) <= END_TIME))
			return true;
		else
			return false;
	
	}
	
}
?>
