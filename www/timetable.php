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
		$this->t_body->set_block("tplBODY", "timetable", "timetable_listing");
		$this->t_body->set_block("timetable", "period", "period_listing");
		$this->t_body->set_block("period", "day", "day_listing");
		$this->t_body->set_block("timetable", "day_header", "day_header_listing");
		
		include_once("include/page.php");
	}

	function getUserInfo($sid)
	{
		$db = new dbManager;
		$query = "SELECT title, firstname, lastname, year, group_id, userCampus.campus 
			FROM user JOIN userCampus ON id = uid WHERE synergy_id = {$sid}";
		return $db->dbGetSingleRow($query);
		
	}

	function displayWeek($sid)
	{
	     
		$user = $this->getUserInfo($sid);
		$this->t_body->set_var("PERSON", formatName($user));
		if($user['year'])
			$this->t_body->set_var("YEAR", ', Year '.$user['year']);
		else
			$this->t_body->set_var("YEAR", "");
		 
		$periodCurrent = getPeriodNumber($user['campus']);
		$dayCurrent = getDayNumber($user['campus']);	
					
		$timetable = getFullTimetable($sid, $user['group_id'], $user['campus']);

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
					else if($_SESSION['campus'][0] == 2 && SITE_location == 'wenona')
					{
							$this->t_body->set_var('PERIOD_DESC', $period['description']);
							$this->t_body->set_var('PERIOD_CODE','');
							$this->t_body->set_var('PERIOD_ROOM',$period['room']);
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
					
				if($dayCount == $dayCurrent && $row['periodNumber'] == $periodCurrent['PeriodNumber'] && $row['periodSequence'] == $periodCurrent['PeriodNumberSeq'] )
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
		$this->t_body->parse("timetable_listing", "timetable", true);
		
		//reset blocks in case we coming back...
		$this->t_body->set_var('period_listing', '');
		$this->t_body->set_var("day_header_listing", '');
	}
	
	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
}

//synergy not enabled do not display page
if(!SYNERGY_enable)
	error(19);

$timetable = new Timetable();

if(isParent($_SESSION['gid']))
{
	foreach($_SESSION['children'] as $child)
	{
		$timetable->displayWeek($child['sid']);
	}
	$timetable->showPage();
}
else
{
	if($_GET['sid'])
	{
		if(isStaff($_SESSION['gid']))
			$timetable->displayWeek($_GET['sid']);
		else
			error(19);
	}
	else
		$timetable->displayWeek($_SESSION['sid']);
	
	$timetable->showPage();
}


?>
