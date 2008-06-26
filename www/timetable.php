<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//timetable object that displays the users timetable

require_once 'include/permission.php';
require_once 'library/synergetic-timetable.php';
require_once 'library/user.php';

//synergy not enabled do not display page
if(!SYNERGY_enable)
	error(19);

$this->setFile(TEMPLATE_content, 'timetable.html');
$this->setTitle('Timetable');
$this->setTab('timetable');

$this->setBlock(TEMPLATE_content, "timetable", "timetable_listing");
$this->setBlock("timetable", "period", "period_listing");
$this->setBlock("period", "day", "day_listing");
$this->setBlock("timetable", "day_header", "day_header_listing");

if(isParent($_SESSION['gid']))
{
	foreach($_SESSION['children'] as $child)
	{
		displayWeek(&$this, $child['sid']);
	}
}
else
{
	if($_GET['sid'])
	{
		if(isStaff($_SESSION['gid']))
			displayWeek(&$this, $_GET['sid']);
		else
			error(19);
	}
	else
		displayWeek(&$this, $_SESSION['sid']);
}



function displayWeek(&$page, $sid)
{
 	$userHandler = new User;
	$timetableHandler = new SynergeticTimetable;
	
	$user = $userHandler->getBySynergy($sid);
     
	$outputUser = array('PERSON'	=> formatName($user),
						'YEAR'		=> $user['year']?', Year '.$user['year']:'',
						);
	$page->setVar($outputUser);
	
	$periodCurrent = $timetableHandler->getPeriodNumber($user['campus']);
	$dayCurrent = $timetableHandler->getDayNumber($user['campus']);	
					
	$timetable = $timetableHandler->getFullTimetable($sid, $user['group_id'], $user['campus']);
	if(is_array($timetable)) {
		$days = count($timetable[0]['day']);	
		//timetable field width
		$page->setVar('TIMETABLE_FIELD_WIDTH', round((95 / $days),1));
		$periodCount = 1;
		//for each period
		foreach($timetable as $row)
		{
			$outputPeriodDefinition = array(	'PERIOD_NAME' 	=> $row['periodName'],
												'PERIOD_START' 	=> $row['periodStart'],
												'PERIOD_END'	=> $row['periodEnd'],
											);
			$page->setVar($outputPeriodDefinition);
				
			$dayCount = 1;
			//for each day
			foreach($row['day'] as $period)
			{	
				if($periodCount == 1) // only output header on first run through
				{
					//output day header
					$page->setVar("DAY_NUMBER", $dayCount);
					$page->parse("day_header_listing", "day_header", true);	
				}
	
				$outputPeriod = array(	'PERIOD_DESC' 	=> '',
										'PERIOD_CODE'	=> '',
										'PERIOD_ROOM'	=> '',
										'PERIOD_ONCLICK'=> '',
										);
	
				if($period['description'])
				{
					if ($_SESSION['campus'][0] == 3 && SITE_location == 'mgs')
					{
						//at mgs grimwade if period contains en its a homeroom class
						$post = strpos($period['code'], "EN");
						if ($post == 2)
							$outputPeriod['PERIOD_DESC'] = 'Homeroom';
						else
						{
							$outputPeriod = array(	'PERIOD_DESC' 	=> $period['description'],
													'PERIOD_ROOM'	=> $period['room'],
												);
						}
						
					}
					else if($_SESSION['campus'][0] == 2 && SITE_location == 'wenona')
					{
						$outputPeriod = array(	'PERIOD_DESC' 	=> $period['description'],
												'PERIOD_ROOM'	=> $period['room'],
											);
					}
					else 
					{
						$outputPeriod = array(	'PERIOD_DESC' 	=> $period['description'],
												'PERIOD_CODE'	=> '('.$period['code'].')',
												'PERIOD_ROOM'	=> $period['room'],
												'PERIOD_ONCLICK'=> 'onClick="window.location=\'/homepage.php?code='.urlencode($period['code']).'\'"',
												);
					}
				}	

				if($dayCount == $dayCurrent && $row['periodNumber'] == $periodCurrent['PeriodNumber'] && $row['periodSequence'] == $periodCurrent['PeriodNumberSeq'] )
					$outputPeriod['TIMETABLE_CLASS'] = 'current';
				else	
					$outputPeriod['TIMETABLE_CLASS'] = 'normal';
				
				$page->setVar($outputPeriod);

				$page->parse('day_listing', 'day', true);
				$dayCount++;
			}
				
				$page->parse('period_listing', 'period', true);
				$page->setVar('day_listing', '');
				$periodCount++;
			}
		$page->parse("timetable_listing", "timetable", true);
		
		//reset blocks in case we coming back...
		$page->set_var('period_listing', '');
		$page->set_var('day_header_listing', '');
	}
	else 
		$this->t_body->set_var('timetable_listing', '');
			
}

?>
