<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//timetable object that displays the users timetable

require_once("include/session.php");
require_once("include/include.php");
require_once("weather/weather.php");
require_once("news/news.php");
require_once("calendar/calendar.php");


class Summary
{

	function Summary()
	{
		//output week to template
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "summary.htm");
		$this->t_body->set_var("TITLE", HEADING." - Summary");


		
		include_once("include/page.php");
		
		
	}
	
	function outputDailyNotices() {
		$myNews = new news($topic);
		$this->t_body->set_var('DAILY_BODY',  $myNews->getDailyNotice());
		
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
				$this->t_body->set_var("NEWS_TITLE", $item['item_heading']);
				$this->t_body->set_var("NEWS_BRIEF", $item['item_body']); 
				$this->t_body->parse("stories_block", "main_stories", true);
	    	}
		}
		else
		{
        	$this->t_body->set_var("stories_block", "");
		}
		
		return true;
	}

	function outputRSS($location) {
		$this->t_body->set_block($location, "rss_stories", "rss_block");
		
		require_once('include/lastRSS.php');
		$rss = new lastRSS; 
		
		$rss->cache_dir = RSS_cache_dir; 
  		$rss->cache_time = RSS_cache_time; // one hour
		$rss->items_limit = RSS_limit;
		$headlineList = $rss->get(RSS_url);
			
		$this->t_body->set_var('RSS_NAME', $headlineList['title']);
		
		//output headlines
		if(is_array($headlineList['items']) ) {
			foreach($headlineList['items'] as $item) {
				$this->t_body->set_var("RSS_TITLE", scrubTEXT($item['title']));
				$this->t_body->set_var("RSS_BRIEF", scrubTEXT($item['description']));
				$this->t_body->parse("rss_block", "rss_stories", true);
	    	}
		}
		else
		{
			return false;
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
	
	function outputWeather() {
		$weather = new Weather("All", WEATHER_LOCATION);
		
		for($i = 0; $i < 3; $i++)
		{
			//set days
			$this->t_body->set_var("DAY".($i+1), $weather->forecast[$i]["Day"]);

			//set image
			$this->t_body->set_var("IMAGE".($i+1), $weather->forecast[$i]["Icon"]);
			
			//set forecast
			$this->t_body->set_var("PRECIS".($i+1), $weather->forecast[$i]["Precis"]);
		
			//set high
			$this->t_body->set_var("HIGH".($i+1), $weather->forecast[$i]["Max"]);
	
			//set lows
			$this->t_body->set_var("LOW".($i+1), $weather->forecast[$i]["Min"]);
		}
	
	}

	
	function showPage()
	{
		$this->outputDailyNotices();
		$this->outputNews("tplBODY");
		$this->outputRSS("tplBODY");
		$this->outputCalendar("tplBODY");
		$this->outputWeather();		
		
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
}


$summary = new Summary();
$summary->showPage();


?>
