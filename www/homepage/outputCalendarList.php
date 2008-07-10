<?php
		//event output
		$this->setBlock($componentHandle, "group_events", "group_events_block");
	
		require_once '../library/calendar.php';
		$calendarHandler = new Calendar;
	
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
				WHERE access = 3 AND eventCampus.campusID = 3 AND ( date >= DATE(NOW()) $where) ORDER BY date, startTime";
		$eventList = $this->db->getAll($query);	

		if (is_array($eventList)) {
			foreach ($eventList as $event) {
		
				$event['colour'] = $calendarHandler->displayColour($event['priority']);
		
				$this->setVar("CAL_DATE", $event['outputDate']);
				$this->setVar("CAL_YEAR", $event['year']);
				$this->setVar("CAL_MONTH", $event['month']);
				$this->setVar("CAL_DAY", $event['day']);
			
				if($event['startTimeString'] == "0:00" || $event['startTimeString'] == "")
						$this->setVar("CAL_TIME", "");
				else if($event['endTimeString'] == "0:00")
						$this->setVar("CAL_TIME", $event['startTimeString']);
				else
						$this->setVar("CAL_TIME", $event['startTimeString']." - ".$event['endTimeString']);
		
				$this->setVar("CAL_COLOUR",$event['colour']);
				
				$this->setVar("CAL_NOTE", nl2br(scrubTEXT($event['note'])));
		
				$this->parse("group_events_block", "group_events", true);
		
			}
		} else
			$this->setVar("group_events_block", '');




		//setup block for submission button
		$this->setBlock($componentHandle, "submit_event", "submit_event_block");
		if(CATEGORY_perms < 2)		
			$this->setVar("submit_event_block", "");
		else
			$this->parse("submit_event_block", "submit_event");	
		
?>