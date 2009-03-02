<?php
//Reset ON_OFF
$j=0;
		//event output
		$this->setBlock($componentHandle, "group_events", "group_events_block");
		$this->setBlock('group_events', "modify", "modify_block");
		
		require_once '../library/calendar.php';
		$calendarHandler = new Calendar;
	
		//event limit changed from posts to days
		if($config['size'] != 0 && $config['size'] != '')
				$where = " AND date < ADDDATE(CURDATE(), INTERVAL {$config['size']} DAY) ";
		else
				$where = "";
		
		$this->setVar('CALENDAR_TITLE', 'Campus Calendar');
		
		$query = "SELECT id, DATE_FORMAT(date, '%a %D %b') as outputDate,
					DATE_FORMAT(date, '%Y') as year, DATE_FORMAT(date, '%c') as month, DATE_FORMAT(date, '%e') as day,
					TIME_FORMAT(startTime, '".CALENDAR_time_format."') as startTimeString, TIME_FORMAT(endTime, 
					'".CALENDAR_time_format."') as endTimeString,priority, note
				FROM event
        			JOIN eventCampus ON event.id = eventCampus.eventID
					JOIN eventGroup ON event.id = eventGroup.eventID
				WHERE access = 3 
					AND eventCampus.campusID IN(".implode(",", $_SESSION['campus']).") 
					AND eventGroup.groupID = {$_SESSION['gid']} 
					AND ( date >= CURRENT_DATE() $where) 
				ORDER BY date, startTime";
		
		$eventList = $this->db->getAll($query);	
		
		$this->setVar('group_events_block', '');
		
		if (is_array($eventList)) {
			
			foreach ($eventList as $event) {
		
				$event['colour'] = $calendarHandler->displayColour($event['priority']);
		
				$output = array('CAL_DATE'	=> $event['outputDate'],
								'CAL_YEAR'	=> $event['year'],
								'CAL_MONTH'	=> $event['month'],
								'CAL_DAY'	=> $event['day'],
								'CAL_ID'	=> $event['id'],
								'ON_OFF'	=> ++$j%2? '' : 'dark',
								);
				
				$this->setVar($output);
			
				if($event['startTimeString'] == "12:00AM" || $event['startTimeString'] == "")
						$this->setVar("CAL_TIME", "");
				else if($event['endTimeString'] == "12:00AM")
						$this->setVar("CAL_TIME", $event['startTimeString']);
				else
						$this->setVar("CAL_TIME", $event['startTimeString']." - ".$event['endTimeString']);
		
				$this->setVar("CAL_COLOUR",$event['colour']);
				
				$this->setVar("CAL_NOTE", nl2br(scrubTEXT($event['note'])));
		
				$this->setVar('modify_block', '');
				if ($calendarHandler->getPerm($event['id']) >= WRITE_ACCESS)
					$this->parse('modify_block', 'modify');
		
				$this->parse("group_events_block", "group_events", true);
				
				
			}
		} else
			$this->setVar("group_events_block", '');




		//setup block for submission button
		$this->setBlock($componentHandle, "submit_event", "submit_event_block");
		if($permission < WRITE_ACCESS)		
			$this->setVar("submit_event_block", "");
		else
			$this->parse("submit_event_block", "submit_event");	
		
?>