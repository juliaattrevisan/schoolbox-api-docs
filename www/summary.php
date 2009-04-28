<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//timetable object that displays the users timetable

require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php');
require_once('../library/weather.php');
require_once('../library/news.php');
require_once('../library/calendar.php');
require_once('include/lastRSS.php');

$page = new SitePage(false);

$page->setFile(TEMPLATE_body, 'summary.html');
$page->setTitle('Summary');
$page->setBlock(TEMPLATE_body, "note_block", "note_listing");

$myNews = new news($topic);
$dNotice = $myNews->getDailyNotice();
$page->setVar('DAILY_TITLE', scrubText($dNotice[0]['item_heading']));
$page->setVar('DAILY_BODY',  $dNotice[0]['item_body']);

//output calendar	
$cal = new Calendar;

$currDay = time();

$page->setVar("DATE", date("l jS F"));

$eventList = $cal->getDayByGroup($currDay, unserialize(STUDENTGROUPIDS), array(1,2,3));

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
		$page->setVar("NOTE_DESCRIPTION", $event['note']);
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
