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
				  FROM event WHERE access = '2' AND sid = '".$categoryID."' AND( date >= NOW() $where) ORDER BY date, startTime";
		
		$this->db->query($query);	

		if($this->db->countRows() < 1){$this->setVar("group_events_block", "");}
		else
		{
			require_once("calendar/calendar.php");
			$cal = new calendar;			

			while($queryArray = $this->db->getNextArray() )
			{
				$queryArray['colour'] = $cal->displayColour($queryArray['priority']);

				$this->setVar("CAL_DATE", $queryArray['outputDate']);
				$this->setVar("CAL_YEAR", $queryArray['year']);
				$this->setVar("CAL_MONTH", $queryArray['month']);
				$this->setVar("CAL_DAY", $queryArray['day']);
			
				if($queryArray['startTimeString'] == "0:00" || $queryArray['startTimeString'] == "")
						$this->setVar("CAL_TIME", "");
				else if($queryArray['endTimeString'] == "0:00")
						$this->setVar("CAL_TIME", $queryArray['startTimeString']);
				else
						$this->setVar("CAL_TIME", $queryArray['startTimeString']." - ".$queryArray['endTimeString']);
		
				$this->setVar("CAL_COLOUR",$queryArray['colour']);
				
				$this->setVar("CAL_NOTE", nl2br(scrubTEXT($queryArray['note'])));
		
				$this->parse("group_events_block", "group_events", true);
		

				
			}
		}


		//setup block for submission button
		$this->setBlock($componentHandle, "submit_event", "submit_event_block");
		if(CATEGORY_perms < 2)		
			$this->setVar("submit_event_block", "");
		else
			$this->parse("submit_event_block", "submit_event");	
		
?>