<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
************************************************************/

// This page outputs a resource summary page

require_once("../../include/session.php");
require_once("../../include/include.php");
require_once("../../include/syn_timetable.php");
require_once("conf.php");
require_once("common.php");
require_once("booking.php");
require_once("resource.php");

class page
{
	function dayNames($day)
	{
		$dayNames = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
		return $dayNames[$day];
	}
	
	function timeNames($hour)
	{
		if($hour < 13)
			return $hour.":00 am";
		else
			return ($hour-12).":00 pm";
	}

	function page()
	{		
		//spacings in pixels
		$top = 30;
		$perhour = 46;
		
		if($_GET['id'])
		{
			$this->t_body = new Template(TPL_directory);
			$this->t_body->set_file("tplBODY", "modules/resource/timesheet.htm");
			//include("../../include/page.php");
			$this->t_body->set_var("TITLE", HEADING." - Resource Booking System");
		
			$bookdb = new booking();
			$resdb = new Resource;
		
			$this->t_body->set_block("tplBODY", "col_header", "col_header_block");
			$this->t_body->set_block("tplBODY", "table_row", "table_row_block");
			$this->t_body->set_block("table_row", "table_cell", "table_cell_block");
			$this->t_body->set_block("tplBODY", "booking", "booking_block");
			
			$this->t_body->set_var("ID", $_GET['id']);
			$this->t_body->set_var("TYPE", $_GET['type']);
			
			//lets build this timesheet 
			if($_GET['type'] != "timetable")
			{
				if($_GET['week'])
					$week = $_GET['week'];
				else 
					$week = $curWeek = date('W', strtotime("+1 day"));
				
				$this->t_body->set_var("LASTWEEK", ($week - 1));
				$this->t_body->set_var("NEXTWEEK", ($week + 1));
				
				$firstMonday = get_monday($week);
				$startDay = strtotime("-1 day", $firstMonday); 
				$currentDay = $startDay;
				$col = 98;
				
				//7 days, from 8am till 7pm
				//output headings
				for($i = FIRST_DAY; $i < DAYS_IN_WEEK; $i++)
				{
					$this->t_body->set_var("COL_HEADING", dayNames($i)."<br />".date('j/n', $currentDay));
					
					$this->t_body->parse("col_header_block", "col_header", true);
				
					$bookings = $bookdb->getBookings($_GET['id'], ($currentDay + (START_TIME * 60 * 60)), ($currentDay + (END_TIME * 60 * 60)));
				
					if(is_array($bookings))
					{
						foreach($bookings as $book)
						{
							$bookingDetected = 1;
							$startTime = strtotime($book['start']);
							$endTime = strtotime($book['end']);
							
							$position = $top + ((date('G', $startTime) - START_TIME) * $perhour) + ( (date('i', $startTime) / 60) * $perhour );
							
							$this->t_body->set_var("START", $position);
							$this->t_body->set_var("COLUMN", $col);
							$this->t_body->set_var("LENGTH", (((($endTime - $startTime) / 60) / 60) * $perhour)); 
							
							$this->t_body->set_var("TIME", date('G:i',$startTime) . "-" . date('G:i',$endTime));
							$this->t_body->set_var("CREATOR", $book['firstname']." ".$book['lastname']);
							
							if($book['type'] == 'request')
								$this->t_body->set_var("COLOUR", "#36DBFE");
							else
								$this->t_body->set_var("COLOUR", "#ffff99");
							
							if($_SESSION['su'] || $resdb->isOwner($_SESSION['id'], $_GET['id']) || $book['userid'] == $_SESSION['id'] )
							{
								$modify =  "<a href=\"#\" onClick=\"loadinparent('modBooking.php?id={$_GET['id']}&booking_id={$book['id']}', 1);\">modify</a>";
							
								if(($_SESSION['su'] || $resdb->isOwner($_SESSION['id'], $_GET['id'])) && $book['type'] == 'request')
									$modify .="<br /><a href=\"#\" onClick=\"loadinparent('modBooking.php?authorise={$book['id']}', 1);\">allow</a> / <a href=\"#\" onClick=\"loadinparent('modBooking.php?deny={$book['id']}', 1);\">deny</a>";
							
								$this->t_body->set_var("MODIFY", $modify);
							}
							else
								$this->t_body->set_var("MODIFY", "");
							
							$this->t_body->parse("booking_block", "booking", true);
						}
					}
					
					$currentDay = strtotime("+1 day", $currentDay); 
					
					$col += 96;
					if(!$bookingDetected)
						$this->t_body->set_var("booking_block", "" );
				
				}
				
				$currentDay = $startDay;
				
				
				for($x = START_TIME; $x < END_TIME; $x++)
				{
					$this->t_body->set_var("ROW_HEADING", timeNames($x));
		
					if(($x % 2) == 0)
						$this->t_body->set_var("ROW_TYPE", "ODD");
					else
						$this->t_body->set_var("ROW_TYPE", "EVEN");
						 
					$this->t_body->parse("table_cell_block", "table_cell", false);
					
					for($f = FIRST_DAY +1; $f < DAYS_IN_WEEK; $f++)
					{
						$this->t_body->parse("table_cell_block", "table_cell", true);
					}
				
					$this->t_body->parse("table_row_block", "table_row", true);
				}	
			}
			else
			{
			
				$timetabledef = getTimetableDefinition($_SESSION['campus'][0]);
				
				$periods = $timetabledef['periodCount'];
				$days = $timetabledef['dayCount'];
			
				for($i = 1; $i <= $days; $i++)
				{
					$this->t_body->set_var("COL_HEADING", "Day $i");
					$this->t_body->parse("col_header_block", "col_header", true);
				}
			
				for($x = 1; isset($timetabledef['desc'][$x]); $x++)
				{
					$this->t_body->set_var("ROW_HEADING", "{$timetabledef['desc'][$x]}<br />{$timetabledef['start'][$x]}-{$timetabledef['end'][$x]}");
		
					if(($x % 2) == 0)
						$this->t_body->set_var("ROW_TYPE", "ODD");
					else
						$this->t_body->set_var("ROW_TYPE", "EVEN");
						 
					$this->t_body->parse("table_cell_block", "table_cell", false);
					
					for($f = 1; $f < $days; $f++)
					{
						$this->t_body->parse("table_cell_block", "table_cell", true);
					}
				
					$this->t_body->parse("table_row_block", "table_row", true);
				}
				$this->t_body->set_var("booking_block", "" );
			}
			$this->t_body->pparse("finaloutput", "tplBODY");
		}
	}
}

$page = new page;

			
?>