<?php

require_once("../../include/include.php");

class Event 
{

	function Event()
	{
		$this->db = new dbManager('clearinghouse', 1);
	}
	
	function addEvent($event)
	{
		$event['created'] = 'NOW()';
		
		if($this->db->dbInsertArray('event', $event))
			return $this->db->dbInsertId();
		else
			return false;
	}
	
	function modEvent($event)
	{
		return $this->db->dbUpdateRow('event', 'id', $event);
	}
	
	function deleteEvent($id)
	{
		return $this->db->deleteRow('event', 'id', $id);
	}

	function getSingleEvent($id)
	{
		$query = "SELECT id, e.name, UNIX_TIMESTAMP(start) as start, TIME_TO_SEC(TIMEDIFF(endtime, start)) as duration, cost, description,keywords,
							e.categoryID, c.name as category, e.yearID, y.name as year,
							e.formatID, f.name as format, organiser, venue, address, presenter,
							url, submitter, created 
						FROM event e 
						LEFT JOIN luCategory c ON e.categoryID = c.categoryID
						LEFT JOIN luFormat f ON e.formatID = f.formatID
						LEFT JOIN luYear y ON e.yearID = y.yearID
						WHERE id = $id";
		return $this->db->dbGetSingleRow($query);
	
	}
	
	function getUpcomingEvents($count)
	{
			$query = "SELECT id, e.name, UNIX_TIMESTAMP(start) as start, TIME_TO_SEC(TIMEDIFF(endtime, start)) as duration, 
							cost, description,
							e.categoryID, c.name as category, e.yearID, y.name as year,
							e.formatID, f.name as format, organiser, presenter
						FROM event e 
						LEFT JOIN luCategory c ON e.categoryID = c.categoryID
						LEFT JOIN luFormat f ON e.formatID = f.formatID
						LEFT JOIN luYear y ON e.yearID = y.yearID
						WHERE start > NOW()
						ORDER BY start ASC LIMIT 0, $count";
			return $this->db->dbGetAllRows($query);
	
	}
	
	function eventSearch($keyword=0, $startDate=0, $endDate=0, $minCost=0, $maxCost=0, $queryArray=0, $orderBy=0)
	{
		$query = "SELECT id, e.name, UNIX_TIMESTAMP(start) as start, TIME_TO_SEC(TIMEDIFF(endtime, start)) as duration, 
							cost, description,
							e.categoryID, c.name as category, e.yearID, y.name as year,
							e.formatID, f.name as format, organiser, venue, address, presenter,
							url, submitter, created 
						FROM event e 
						LEFT JOIN luCategory c ON e.categoryID = c.categoryID
						LEFT JOIN luFormat f ON e.formatID = f.formatID
						LEFT JOIN luYear y ON e.yearID = y.yearID
						WHERE";
							 
		if($keyword)
		{
			$query .= " (e.name like '%$keyword%' OR description like '%$keyword%' OR keywords like '%$keyword%' 
								OR organiser like '%$keyword%' OR presenter like '%$keyword%' OR venue like '%$keyword%' )";
		}
		else
			$query .= " 1=1";
					
		if($startDate)
			$query .= " AND start >= FROM_UNIXTIME('$startDate')";
		if($endDate)
			$query .= " AND start <= FROM_UNIXTIME('$endDate')";
		if($minCost)
			$query .= " AND cost >= $minCost";
		if($maxCost)
			$query .= " AND cost <= $maxCost";
			
		if(is_array($queryArray))
		{
			foreach($queryArray as $key => $value)
			{
				if($value)
					$query .= ' AND ' . $key .'='.$value;
			}
		}
		
		if($orderBy)
			$query .= ' ORDER BY $orderBy';

		return $this->db->dbGetAllRows($query);
	}
	
	
}

?>