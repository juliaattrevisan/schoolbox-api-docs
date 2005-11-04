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
require_once("calendar/calendar.php");
require_once("weather/weather.php");
require_once("news/whatsnew.php");

class OutPut
{
	function OutPut()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "home.htm");

		$this->t_body->set_var("TITLE", HEADING." - Home");
		
		$this->t_body->set_block("tplBODY", "timetable", "timetable_block");	
		
		$this->db = new dbManager;
		
		$this->setGreeting();
		
		
		//if user has timetable show it on the homepage
		if($_SESSION['timetable'])
			$this->outputTimetable();
		else
			$this->t_body->set_var("timetable_block", "");


		$this->t_body->set_file("news", "home/news.htm");
		$this->t_body->set_file("weather", "home/weather.htm");
		$this->t_body->set_file("calendar", "home/calendar.htm");
		$this->t_body->set_file("whatnew", "home/changedfolder.htm");
		
		
		if($this->outputWeather("weather"))
			$this->t_body->parse("POS3", "weather");
		else 
			$this->t_body->set_var("POS3", "");
		
		if($this->outputCalendar("calendar"))
			$this->t_body->parse("POS2", "calendar");
		else 
			$this->t_body->set_var("POS2", "");
		
		if($this->outputNews("news"))
			$this->t_body->parse("POS1", "news");
		else 
			$this->t_body->set_var("POS1", "");
		
		if($this->outputWhatNew("whatnew"))
			$this->t_body->parse("POS4", "whatnew");
		else 
			$this->t_body->set_var("POS4", "");
		
		$this->showPage();
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

	function outputNews($location)
	{	
		$this->t_body->set_block($location, "main_stories", "stories_block");
		
		$myNews = new news($topic);
		
		//output headlines
		$headlineList = $myNews->getHeadlines();
		
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
		
		return true;
	}

	function outputCalendar($location)
	{
		$this->t_body->set_block($location, "note_block", "note_listing");
		
		$cal = new Calendar;
		
		$currDay = date("d");
		$currMonth = date("m");
		$currYear = date("Y");
		
		$this->t_body->set_var("DATE", date("l jS F"));
		
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
		
		$eventList = $cal->getAllDayEvents($currDay, $currMonth, $currYear);
		
		if(!is_array($eventList))
		{
			if(!$subout) $this->t_body->set_var("note_listing", "");
		}
		else
		{
			foreach($eventList as $event)
			{
				if($event['startTime'] == "0:00" || strtolower($event['startTime']) == "12:00am" || strtolower($event['startTime']) == "12:00 am" || $event['startTime'] == '')
						$this->t_body->set_var("TIME", "");
				else if($event['endTime'] == "0:00" || strtolower($event['endTime']) == "12:00am" || strtolower($event['endTime']) == "12:00 am" || $event['endTime'] == '')
						$this->t_body->set_var("TIME", $event['startTime']);
				else
						$this->t_body->set_var("TIME", $event['startTime']." - ".$event['endTime']);
				
				$this->t_body->set_var("NOTE_COLOUR", $cal->displayColour($event['priority']));
				$this->t_body->set_var("NOTE", $event['note']);
				$this->t_body->parse("note_listing","note_block", true);

			}
		}
		
		return true;
	}

	
	function outputWeather($location)
	{
		//dont output weather if not enabled
		if(!WEATHER_enable)
			return false;
			
		$this->t_body->set_block($location, "weather", "weather_block");
		$weather = new Weather("Current");
		
		$this->t_body->set_var("WEATHER_ICON", $weather->forecast[0]["Icon"]);
		$this->t_body->set_var("PRECIS", $weather->forecast[0]["Precis"]);
		$this->t_body->set_var("TEMP", $weather->Temp);
		$this->t_body->set_var("DIRECTION", $weather->WindCompass);
		$this->t_body->set_var("SPEED", $weather->WindSpeed);
		$this->t_body->set_var("HUMIDITY", $weather->Humid);
		$this->t_body->set_var("ISSUED", date("g:i a", strtotime(str_replace('T', ' ',$weather->Date))));
	
		$this->t_body->parse("weather_block", "weather");
		
		return true;
	}

	function outputTimetable()
	{
		$this->t_body->set_block("timetable", "timetable_period", "timetable_period_block");
		$this->t_body->set_block("timetable", "timetable_header", "timetable_header_block");

		include("syn_timetable.php");
		
		//get current day and period
		$daynumber = getDayNumber();
		$periodNum = getPeriodNumber();
		
		$this->t_body->set_var("DAYNUMBER", $daynumber);
	

		$timetable = getDayTimetable($_SESSION['sid'], $_SESSION['gid'], $_SESSION['campus'][0], $daynumber);
		
		$periods = count($timetable);

		//timetable field width
		$this->t_body->set_var("TIMETABLE_FIELD_WIDTH", round((100 / $periods),1));

		//for each period set result
		foreach($timetable as $period)
		{
			
			$this->t_body->set_var("PERIOD_NAME", $period['periodName']);
			$this->t_body->set_var("PERIOD_START", $period['periodStart']);
			$this->t_body->set_var("PERIOD_END", $period['periodEnd']);
			
			if($period['description'])
			{
				//custom stuff for grimwade
				if ($_SESSION['campus'][0] == 3 && SITE_location == 'mgs')
				{
					$post = strpos($period['code'], "EN");
						
					if ($post == 2)
					{
						$this->set_var('PERIOD_DESC', 'Homeroom');
						$this->set_var('PERIOD_CODE','');
						$this->set_var('PERIOD_ROOM','');
					}
					else
					{
						$this->set_var('PERIOD_DESC', $period['description']);
						$this->set_var('PERIOD_CODE','');
						$this->set_var('PERIOD_ROOM',$period['room']);
					}
					
					$this->set_var('PERIOD_ONCLICK','');
					$this->set_var('PERIOD_ABSENTEES','');
				}
				else 
				{
					$this->set_var('PERIOD_DESC', $period['description']);
					$this->set_var('PERIOD_CODE','('.$period['code'].')');
					$this->set_var('PERIOD_ROOM',$period['room']);
					
					//only show attendances to staff at mgs
					if(isStaff($_SESSION['gid']) && SITE_location == 'mgs')
						$this->set_var('PERIOD_ABSENTEES',"<br /><a href=\"/absentees/attendances.php?code=".$period['code']."\">attendances</a>");
					else
						$this->set_var('PERIOD_ABSENTEES','');
						
					$this->set_var('PERIOD_ONCLICK',"onClick=\"window.location='/homepage.php?code=".urlencode($period['code'])."'\"");
				}		
			
			}
			else
			{
				$this->set_var('PERIOD_DESC','');
				$this->set_var('PERIOD_CODE','');
				$this->set_var('PERIOD_ROOM','');
				$this->set_var('PERIOD_ABSENTEES','');
				$this->set_var('PERIOD_ONCLICK','');
			}
			
			if($period['periodNumber'] == $periodNum)
				$this->set_var("TIMETABLE_CLASS", "current");
			else 
				$this->set_var("TIMETABLE_CLASS", "normal");
			
			//output period block
			$this->t_body->parse("timetable_header_block", "timetable_header", true);
			$this->t_body->parse("timetable_period_block", "timetable_period", true);

		}

		$this->t_body->parse("timetable_block", "timetable");
	}
	
	function outputWhatNew($location)
	{
		$catList = involvedCategoryList();
		$whatnew = new WhatsNew();
	
		$this->t_body->set_block($location, "category_block", "new_category");
		$this->t_body->set_block("category_block", "new_news", "new_news_item"); 
		$this->t_body->set_block("category_block", "new_file", "new_file_item"); 
		$this->t_body->set_block("category_block", "new_url", "new_url_item"); 
		$flag = 0;

		//for each subject
		for($x = 0; $catList['id'][$x] ; $x++)
		{
			$cat = $whatnew->newCategory($catList['id'][$x]);
			if($cat['news'])
			{
				$this->t_body->set_var("NEW_NEWS", $cat['news']);
				$this->t_body->parse("new_news_item", "new_news");
			}
			else
				$this->t_body->set_var("new_news_item", "");
				
			if($cat['files'])
			{
				$this->t_body->set_var("NEW_FILES", $cat['files']);
				$this->t_body->parse("new_file_item", "new_file");
			}
			else
				$this->t_body->set_var("new_file_item", "");
			
			if($cat['links'])
			{
				$this->t_body->set_var("NEW_URLS", $cat['links']);
				$this->t_body->parse("new_url_item", "new_url");
			}
			else
				$this->t_body->set_var("new_url_item", "");
			
			if($cat['news'] || $cat['files'] || $cat['links'])
			{
				if($catList['code'][$x])
					$this->t_body->set_var("NEW_CATEGORY_NAME", $catList['name'][$x].' ('.$catList['code'][$x].')');
				else
					$this->t_body->set_var("NEW_CATEGORY_NAME", $catList['name'][$x]);
				$this->t_body->set_var("NEW_CATEGORY_ID", $catList['id'][$x]);
				$this->t_body->parse("new_category", "category_block");
				$flag = 1;
			}
			
		}
		
		if(!$flag)
			$this->t_body->set_var('new_category','');
		
		return true;
	}


	function showPage()
	{
		include("include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

}

$myPage = new OutPut();

?>
