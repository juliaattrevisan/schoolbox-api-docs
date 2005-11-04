<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//timetable object that displays the users timetable

require_once("include/session.php");
require_once("include/include.php");
require_once("include/permission.php");
require_once("include/syn_timetable.php");

class Timetable
{

	function Timetable()
	{
		//output week to template
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "timetable.htm");
		$this->t_body->set_var("TITLE", HEADING." - Timetable");
		//setup blocks
		$this->t_body->set_block("tplBODY", "period", "period_listing");
		$this->t_body->set_block("period", "day", "day_listing");
		$this->t_body->set_block("tplBODY", "day_header", "day_header_listing");
		
		include_once("include/page.php");
	}

	function displayWeek()
	{
	     
		$periodNum = getPeriodNumber();
		$dayNum = getDayNumber();	
		
		$timetable = getFullTimetable($_SESSION['sid'], $_SESSION['gid'], $_SESSION['campus'][0]);
		
		$days = count($timetable[0]['day']);
		
		//timetable field width
		$this->t_body->set_var("TIMETABLE_FIELD_WIDTH", round((95 / $days),1));
		
		$periodCount = 1;
		//for each period
		foreach($timetable as $row)
		{
			$this->t_body->set_var("PERIOD_NAME", $row['periodName']);
			$this->t_body->set_var("PERIOD_START", $row['periodStart']);
			$this->t_body->set_var("PERIOD_END", $row['periodEnd']);
			
			$dayCount = 1;
			//for each day
			foreach($row['day'] as $period)
			{	
				if($periodCount == 1) // only output header on first run through
				{
					//output day header
					$this->t_body->set_var("DAY_NUMBER", $dayCount);
	        		$this->t_body->parse("day_header_listing", "day_header", true);	
				}

				if($period['description'])
				{
					if ($_SESSION['campus'][0] == 3 && SITE_location == 'mgs')
					{
						$post = strpos($period['code'], "EN");
					
						if ($post == 2)
						{
							$this->t_body->set_var('PERIOD_DESC', 'Homeroom');
							$this->t_body->set_var('PERIOD_CODE','');
							$this->t_body->set_var('PERIOD_ROOM','');
						}
						else
						{
							$this->t_body->set_var('PERIOD_DESC', $period['description']);
							$this->t_body->set_var('PERIOD_CODE','');
							$this->t_body->set_var('PERIOD_ROOM',$period['room']);
						}
					
						$this->t_body->set_var('PERIOD_ONCLICK','');
					}
					else 
					{
						$this->t_body->set_var('PERIOD_DESC', $period['description']);
						$this->t_body->set_var('PERIOD_CODE','('.$period['code'].')');
						$this->t_body->set_var('PERIOD_ROOM',$period['room']);
						$this->t_body->set_var('PERIOD_ONCLICK',"onClick=\"window.location='/homepage.php?code=".urlencode($period['code'])."'\"");	
					}
				}	
				else
				{
					$this->t_body->set_var('PERIOD_DESC','');
					$this->t_body->set_var('PERIOD_CODE','');
					$this->t_body->set_var('PERIOD_ROOM','');
					$this->t_body->set_var('PERIOD_ONCLICK','');
				}
					
				if($day == $dayNum && $row['periodNumber'] == $periodNum)
					$this->t_body->set_var("TIMETABLE_CLASS", "current");
				else	
					$this->t_body->set_var("TIMETABLE_CLASS", "normal");

				$this->t_body->parse("day_listing", "day", true);
				$dayCount++;
			}
			
        	$this->t_body->parse("period_listing", "period", true);
			$this->t_body->set_var("day_listing", '');
			$periodCount++;
		}
		
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
}

$timetable = new Timetable();

$timetable->displayWeek();


?>
