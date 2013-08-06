<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//timetable object that displays the users timetable

require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php');

require_once('include/lastRSS.php');

$page = new SitePage(false);

$page->setFile(TEMPLATE_body, 'summary.html');
$page->setTitle('Summary');
$page->setBlock(TEMPLATE_body, "note_block", "note_listing");

if (is_numeric($_GET['campus']))
	$campus = array($_GET['campus']);
else
	$campus = array(1,2,3);

if (is_numeric($_GET['group']))
	$group = array($_GET['group']);
else
	$group = array(1,2,3,4,5,12,13);

$myNews = new news($topic);

if ($_GET['news'] == 'all') {
	$dNotice = '';
	$newsList = $myNews->getAllByGroup($group, $campus);
	if (is_array($newsList)) {
		foreach ($newsList as $news) {
			$dNotice .= '<p class="heading">' . Scrub::text($news['item_heading']). '</p>';
			$dNotice .= Scrub::html($news['item_body']) . '<p>&nbsp;</p>';
		}
	}
	$page->setVar('DAILY_TITLE', 'DAILY NOTICES');
	$page->setVar('DAILY_BODY',  $dNotice);

} else {
	$dNotice = $myNews->getDailyNotice(1, $campus);
	$page->setVar('DAILY_TITLE', Scrub::text($dNotice[0]['item_heading']));
	$page->setVar('DAILY_BODY',  $dNotice[0]['item_body']);
}
//output calendar
$cal = new Calendar;

$currDay = time();

$page->setVar("DATE", date("l jS F"));


$groupHandler = new Group();

$eventList = $cal->getDayByGroup($currDay, $groupHandler->getStudentGroupTypeIds(), $campus);

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


//$weather = new Weather("All", WEATHER_LOCATION);
$location = empty($_GET['location']) ? Cfg::get('weather_location') : $_GET['location'];
$weather = new Xml('http://www.google.com/ig/api?weather=' . urlencode("Melbourne, Victoria"), 60*60 );


for($i = 1; $i <= 3; $i++) {
	$forecast_conditions = $weather->weather->forecast_conditions[$i];
	$output = array("DAY$i"     => $forecast_conditions->day_of_week['data'],
					"IMAGE$i"   => $forecast_conditions->icon['data'],
					"PRECIS$i"  => $forecast_conditions->condition['data'],
					"HIGH$i"    => round(($forecast_conditions->high['data'] - 32) / 1.8),
					"LOW$i"     => round(($forecast_conditions->low['data'] - 32) / 1.8),
					);
	$page->setVar($output);

}

$page->outputPage(true);



?>
