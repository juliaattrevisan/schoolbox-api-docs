<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//displays list of files either by search or during browsing.

require_once("include/session.php");
require_once("include/include.php");
require_once("include/permission.php");
require_once("news/news.php");
require_once("weather/weather.php");
require_once("calendar/calendar.php");

class OutPut
{
	function OutPut()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "home.htm");

		$this->t_body->set_var("TITLE", HEADING." - Home");
		
		$this->t_body->set_block("tplBODY", "timetable", "timetable_block");	
		$this->t_body->set_block("tplBODY", "main_stories", "stories_block");
		$this->t_body->set_block("tplBODY", "weather", "weather_block");
		$this->t_body->set_block("tplBODY", "note_block", "note_listing");	

		$this->db = new dbManager;
		
		$this->setGreeting();
		
		//if staff get all news.. 
		if(isStaff($_SESSION['gid']))
			$topic = 0;
		else //student only sees news for everyone
			$topic = 2;
		
		$myNews = new news($topic);
		//output headlines
		$this->setStories($myNews->getHeadlines());
		
		//if user has timetable show it on the homepage
		if($_SESSION['timetable'])
			$this->setTimetable();
		else
			$this->t_body->set_var("timetable_block", "");

		//if weather module enabled output it
		if(WEATHER_enable)
			$this->setWeather();
		else
			$this->t_body->set_var("weather_block", "");
		
		//output calendar
		$this->setDayEvents();
		
		include("include/page.php");
	}


	function set_var($varName, $varContents)
	{
		$this->t_body->set_var($varName, $varContents);
	}
	
	function greeting()
	{
		if(date("A")=="AM")
			return "Good Morning";
		else if(date("G") < 17)
			return "Good Afternoon";
		else
			return "Good Evening";
	}

	function setGreeting()
	{
		$query = "SELECT firstname FROM user WHERE 
				id = '".$_SESSION['id']."'";
		$user = $this->db->dbGetSingleRow($query);
		$this->set_var("GREETING", $this->greeting()." ".$user['firstname']);
	}

	function setStories($headlineList)
	{
		if(is_array($headlineList) )
		{
			foreach($headlineList as $item)
			{
				$this->set_var("NEWS_TITLE", $item['item_heading']);
				$this->set_var("NEWS_URL", "/news.php#".$item['item_id']); 
				$this->t_body->parse("stories_block", "main_stories", true);
	    	}
		}
		else
		{
        	$this->set_var("stories_block", "<li>No news articles available</li>");
		}
	}

	function setDayEvents()
	{
		$cal = new Calendar;
		
		$currDay = date("d");
		$currMonth = date("m");
		$currYear = date("Y");
		
		$currentDay = mktime();
		$this->t_body->set_var("DATE", date("l jS F",$currentDay));
		
		//first lets output submission events
		$sub = $cal->getSubmissionEvents($currDay, $currMonth, $currYear);
		for($i=0; $sub['category'][$i]; $i++)
		{
			$this->t_body->set_var("TIME", $sub['dueTime'][$i]);
			if($sub['synergyCode'][$i])
				$descr = $sub['category'][$i]." (".$sub['synergyCode'][$i].") - ".$sub['description'][$i];
			else
				$descr = $sub['category'][$i]." - ".$sub['description'][$i];
				
			$this->t_body->set_var("NOTE", "WORK DUE ".$descr); 
			$this->t_body->set_var("NOTE_COLOUR", $cal->displayColour(1));
			
			$this->t_body->parse("note_listing","note_block", true);
			$subout = 1;
		}
		
		$event = $cal->getAllDayEvents($currDay, $currMonth, $currYear);
		
		if(count($event['note']) < 1 && !$subout) 
		{
			$this->t_body->set_var("note_listing", "");
		}
		else
		{
			for($i=0; isset($event['note'][$i]); $i++)
			{
				if($event['startTime'][$i] == "0:00" || strtolower($event['startTime'][$i]) == "12:00am" || strtolower($event['startTime'][$i]) == "12:00 am")
						$this->t_body->set_var("TIME", "");
				else if($event['endTime'][$i] == "0:00" || strtolower($event['endTime'][$i]) == "12:00am" || strtolower($event['endTime'][$i]) == "12:00 am")
						$this->t_body->set_var("TIME", $event['startTime'][$i]);
				else
						$this->t_body->set_var("TIME", $event['startTime'][$i]." - ".$event['endTime'][$i]);
				
				$this->t_body->set_var("NOTE_COLOUR", $cal->displayColour($event['priority'][$i]));
				$this->t_body->set_var("NOTE", $event['note'][$i]);
				$this->t_body->parse("note_listing","note_block", true);

			}
		}
	}

	
	function setWeather()
	{
		$weather = new Weather("Current");
		
		$this->t_body->set_var("TEMP", $weather->Temp);
		$this->t_body->set_var("DIRECTION", $weather->WindC);
		$this->t_body->set_var("SPEED", $weather->WindS);
		$this->t_body->set_var("HUMIDITY", $weather->Humid);
		$this->t_body->set_var("ISSUED", date("g:i a", strtotime($weather->Date)));

		$this->t_body->parse("weather_block", "weather");
	}

	function setTimetable()
	{
		$this->t_body->set_block("timetable", "timetable_period", "timetable_period_block");
		$this->t_body->set_block("timetable", "timetable_header", "timetable_header_block");

		//setup database
		$db = mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);

		$year = date("Y");
		$synergy_id = $_SESSION['sid'];

		//need method for detecting what day it is
		include("syn_timetable.php");
		$daynumber = getDayNumber();
		
		$this->t_body->set_var("DAYNUMBER", $daynumber);
		
		//also get current peroid
		$periodNum = getPeriodNumber();

		//retrieve timetable defintion
		$timetabledef = unserialize(TIMETABLE_definition);

		$query = getDayTimetableQuery($synergy_id, $year, $daynumber);
		
		$result = mssql_query($query, $db);
		if ($result != 1)
			$desc = mssql_fetch_array($result);
		else
			$desc = "";

		$periods =  $timetabledef[$_SESSION['campus'][0]]['period'];

		//timetable field width
		$this->t_body->set_var("TIMETABLE_FIELD_WIDTH", round((100 / $periods),1));

		//for each period set result
		for($y=1; $y <= $periods; $y++)
		{
			if($desc['PeriodNumber'] == ($y-1))
			{
				if ($result != 1)
					$desc = mssql_fetch_array($result);
				else
					$desc = "";
			}
			
			if($desc['PeriodNumber'] != $y)
			{
				$code = "";
				$period = "-";
			}
			else
			{
				//custom stuff for grimwade
				if ($_SESSION['campus'][0] == 3 && SITE_location == 'mgs')
				{
					$post = strpos($desc['ClassCode'], "EN");
					
					if ($post == 2)
						$period = "Homeroom";
					else
						$period = $desc['Description']."<br />".$desc['Room']."<br />";
			
					$code = "";
				}
				else 
				{
					$period = $desc['Description']."<br />(".$desc['ClassCode'].")<br />".$desc['Room']."<br />";
					
					//only show attendances to staff at mgs
					if(isStaff($_SESSION['gid']) && SITE_location == 'mgs')
						$period .= "<a href=\"/absentees/attendances.php?code=".$desc['ClassCode']."\">attendances</a><br>";
					
					$code = "onClick=\"window.location='/homepage.php?code=".urlencode($desc['ClassCode'])."'\"";
				}		
				
				$desc = mssql_fetch_array($result);
			}
			
			$this->set_var("CODE", $code);
			$this->set_var("PERIOD", $period);
			
			if($y == $periodNum)
				$this->set_var("TIMETABLE_CLASS", "current");
			else 
				$this->set_var("TIMETABLE_CLASS", "normal");
			
			//output period block
			$this->set_var("PERIOD_NAME", $timetabledef[$_SESSION['campus'][0]]['periodnames'][$y]);
			$this->t_body->parse("timetable_header_block", "timetable_header", true);
			$this->t_body->parse("timetable_period_block", "timetable_period", true);

		}

		$this->t_body->parse("timetable_block", "timetable");
	}


	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

}

$myPage = new OutPut();
$myPage->showPage();
?>
