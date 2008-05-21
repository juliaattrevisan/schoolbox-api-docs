<?php
		//event output
		$this->setBlock($componentHandle, "group_events", "group_events_block");
	
		//event limit changed from posts to days
		if($config['size'] != 0 && $config['size'] != '')
				$where = " AND date < ADDDATE(CURDATE(), INTERVAL {$config['size']} DAY) ";
		else
				$where = "";
		
		
		
		$query = "SELECT id, DATE_FORMAT(date, '%a %D %b') as outputDate,
						DATE_FORMAT(date, '%Y') as year, DATE_FORMAT(date, '%c') as month, DATE_FORMAT(date, '%e') as day,
						TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTimeString, TIME_FORMAT(endTime, 
				'".CALENDAR_time_format."') as endTimeString,priority, note
				FROM event
        				JOIN eventCampus ON event.id = eventCampus.eventID
				WHERE access = 3 AND eventCampus.campusID = 3 AND( date >= DATE(NOW()) $where) ORDER BY date, startTime";
		$this->db->query($query);	

		if($this->db->countRows() < 1){$this->setVar("group_events_block", "");}
		else
		{
			require_once("calendar/calendar.php");
			$cal = new calendar;			

			while($queryArray = $this->db->getNextArray() )
			{
				$queryArray['colour'] = $cal->displayColour($queryArray['priority']);
				$this->outputEvent($queryArray);
			}
		}


		//setup block for submission button
		$this->setBlock($componentHandle, "submit_event", "submit_event_block");
		if(CATEGORY_perms < 2)		
			$this->setVar("submit_event_block", "");
		else
			$this->parse("submit_event_block", "submit_event");	
		
?>