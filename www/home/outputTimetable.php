<?php

require_once '../library/synergetic-timetable.php';
	
$this->setBlock(TEMPLATE_content, "timetable", "timetable_block");	
$this->setBlock("timetable", "timetable_period", "timetable_period_block");
$this->setBlock("timetable", "timetable_header", "timetable_header_block");

//if user has timetable show it on the homepage
if($_SESSION['timetable'] && SYNERGY_enable ) {
	$timetableHandler = new SynergeticTimetable;
	//get current day and period
	$daynumber = $timetableHandler->getDayNumber($_SESSION['campus'][0]);
	$period = $timetableHandler->getPeriodNumber($_SESSION['campus'][0]);
	$periodNum = $period['PeriodNumber'];
	
	$this->setVar("DAYNUMBER", $daynumber);
	
	$timetable = $timetableHandler->getDayTimetable($_SESSION['sid'], $_SESSION['id'], $_SESSION['campus'][0], $daynumber);
	
	$periodCount=0;
	
	if(is_array($timetable)) {
		
		//for each period set result
		foreach($timetable as $period)
		{
			if($period['periodSequence'] == 1)
			{
				$this->setVar("PERIOD_NAME", $period['periodName']);
				$this->setVar("PERIOD_START", $period['periodStart']);
				$this->setVar("PERIOD_END", $period['periodEnd']);
				$periodCount++; 
				
				if($period['description'])
				{
					//custom stuff for grimwade
					if ($_SESSION['campus'][0] == 3 && SITE_location == 'mgs')
					{
						$post = strpos($period['code'], "EN");
							
						if ($post == 2)
						{
							$this->setVar('PERIOD_DESC', 'Homeroom');
							$this->setVar('PERIOD_CODE','');
							$this->setVar('PERIOD_ROOM','');
						}
						else
						{
							$this->setVar('PERIOD_DESC', $period['description']);
							$this->setVar('PERIOD_CODE','');
							$this->setVar('PERIOD_ROOM',$period['room']);
						}
						
						$this->setVar('PERIOD_ABSENTEES','');
					}
					else if($_SESSION['campus'][0] == 2 && SITE_location == 'wenona')
					{
							$this->setVar('PERIOD_DESC', $period['description']);
							$this->setVar('PERIOD_CODE','');
							$this->setVar('PERIOD_ROOM',$period['room']);
							$this->setVar('PERIOD_ABSENTEES','');
					}
					else 
					{
						$this->setVar('PERIOD_DESC', "<a 
		href=\"/homepage.php?code=".urlencode($period['code'])."\">{$period['description']}</a>");
						$this->setVar('PERIOD_CODE','('.$period['code'].')');
						$this->setVar('PERIOD_ROOM',$period['room']);
						
						//only show attendances to staff at mgs
						/*if(isStaff($_SESSION['gid']) && SITE_location == 'mgs')
							$this->setVar('PERIOD_ABSENTEES',"<br /><a href=\"/absentees/attendances.php?code=".$period['code']."\">attendances</a>");
						else */
						$this->setVar('PERIOD_ABSENTEES','');
							
					}		
				
				}
				else
				{
					$this->setVar('PERIOD_DESC','');
					$this->setVar('PERIOD_CODE','');
					$this->setVar('PERIOD_ROOM','');
					$this->setVar('PERIOD_ABSENTEES','');
					$this->setVar('PERIOD_ONCLICK','');
				}
			
				if($period['periodNumber'] == $periodNum)
					$this->setVar("TIMETABLE_CLASS", "current");
				else 
					$this->setVar("TIMETABLE_CLASS", "normal");
			
			
				//output period block
				$this->parse("timetable_header_block", "timetable_header", true);
				$this->parse("timetable_period_block", "timetable_period", true);
			}
		} 
		
				//timetable field width
		$this->setVar("TIMETABLE_FIELD_WIDTH", floor((( 98 - ($periodCount * 0.05) ) / $periodCount)));
		
		$this->parse("timetable_block", "timetable");
	}
	else
		$this->setVar("timetable_block", "");
}
else
	$this->setVar("timetable_block", "");
?>
