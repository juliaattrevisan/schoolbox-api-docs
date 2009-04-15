<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//timetable object that displays the users timetable

require_once('../include/sitePage.php');
require_once('../library/weather.php');
require_once('../library/news.php');
require_once('../library/calendar.php');
require_once('include/lastRSS.php');

set_time_limit(5);
session_start();


if (is_numeric($_GET['id']))
	$_SESSION['id'] = $_GET['id'];
else
	$_SESSION['id'] = 4579;
	
$_SESSION['campus'][] = 1;
$_SESSION['gid'] = 3;

$page = new SitePage(false);

$page->setFile(TEMPLATE_body, 'summary.htm');
$page->setTitle('Summary');
$page->setBlock(TEMPLATE_body, "main_stories", "stories_block");
$page->setBlock(TEMPLATE_body, "rss_stories", "rss_block");
$page->setBlock(TEMPLATE_body, "note_block", "note_listing");

$myNews = new news($topic);
$dNotice = $myNews->getDailyNotice();
$page->setVar('DAILY_TITLE', scrubText($dNotice['item_heading']));
$page->setVar('DAILY_BODY',  $dNotice['item_body']);

$headlineList = $myNews->getNews();	
if(is_array($headlineList) )
{
	foreach($headlineList as $headline) {
		
		$length = count($headlineList);
		if ($length > 8) $length = 8;
		
		for($i = 0; $i < $length; $i++)
		{
			if (stristr($headlineList[$i]['item_heading'], 'Daily Notices')) { $length++; continue; }
			
			$page->setVar("NEWS_TITLE", $headlineList[$i]['item_heading']);
			$page->setVar("NEWS_BRIEF", $headlineList[$i]['item_body']); 
			$page->parse("stories_block", "main_stories", true);
		}
	}
}
else
{
	$page->setVar("stories_block", "");
}

		
$rss = new lastRSS; 

$rss->cache_dir = RSS_cache_dir; 
$rss->cache_time = RSS_cache_time; // one hour
$rss->items_limit = RSS_limit;
$headlineList = $rss->get(RSS_url);
	
$page->setVar('RSS_NAME', $headlineList['title']);

//output headlines
if(is_array($headlineList['items']) ) {
	foreach($headlineList['items'] as $item) {
		$page->setVar("RSS_TITLE", scrubTEXT($item['title']));
		$page->setVar("RSS_BRIEF", scrubTEXT($item['description']));
		$page->parse("rss_block", "rss_stories", true);
	}
}

	
//output calendar	
$cal = new Calendar;

$currDay = time();
$currMonth = date("m");
$currYear = date("Y");

$page->setVar("DATE", date("l jS F"));

//first lets output submission events
$submissionList = $cal->getSubmissionEvents($currDay);
if (is_array($submissionList)) {
	foreach ($submissionList as $submission) {
	
		$output = array('TIME'				=> $submission['dueTime'],
						'NOTE_CATEGORY'		=> 'WORK DUE ' . $submission['category'] . (isset($submission['synergyCode']) ? ' ('.$submission['synergyCode'].')' : '') . ' - ',
						'NOTE_DESCRIPTION'	=> $submission['description'],
						'NOTE_COLOUR'		=> $calendarHandler->displayColour(1),
						);
			
		$page->setVar($output); 
		
		$page->parse("note_listing","note_block", true);
		$subout = 1;
	}
}		


$eventList = $cal->getDayEvents($currDay, $currMonth, $currYear);

if(!is_array($eventList))
{
	if(!$subout) $page->setVar("note_listing", "");
}
else
{
	foreach($eventList as $event)
	{
		if($event['startTime'] == "0:00" || strtolower($event['startTime']) == "12:00am" || strtolower($event['startTime']) == "12:00 am" || $event['startTime'] == '')
				$page->setVar("TIME", "");
		else if($event['endTime'] == "0:00" || strtolower($event['endTime']) == "12:00am" || strtolower($event['endTime']) == "12:00 am" || $event['endTime'] == '')
				$page->setVar("TIME", $event['startTime']);
		else
				$page->setVar("TIME", $event['startTime']." - ".$event['endTime']);
		
		$page->setVar("NOTE_COLOUR", $cal->displayColour($event['priority']));
		$page->setVar("NOTE", $event['note']);
		$page->parse("note_listing","note_block", true);

	}
}


$weather = new Weather("All", WEATHER_LOCATION);

for($i = 0; $i < 3; $i++)
{
	//set days
	$page->setVar("DAY".($i+1), $weather->forecast[$i]["Day"]);

	//set image
	$page->setVar("IMAGE".($i+1), $weather->forecast[$i]["Icon"]);
	
	//set forecast
	$page->setVar("PRECIS".($i+1), $weather->forecast[$i]["Precis"]);

	//set high
	$page->setVar("HIGH".($i+1), $weather->forecast[$i]["Max"]);

	//set lows
	$page->setVar("LOW".($i+1), $weather->forecast[$i]["Min"]);
}

$page->outputPage(true);



?>
