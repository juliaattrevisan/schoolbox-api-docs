<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/calendar.php';

$page = new SitePage(false);
$calendarHandler = new Calendar;

$page->setFile(TEMPLATE_body, 'feed/rss.xml');
$page->setBlock(TEMPLATE_body, 'rss_item', 'rss_item_block');

$output = array('TITLE'			=> HEADING . ' - Calendar',
				'DESCRIPTION'	=> 'Today\'s events',
				'DATE'			=> date('r'),
				'LINK'			=> SITE_address . '/calendar/week/',
				);
$page->setVar($output);

$calendarList = $calendarHandler->getDayByGroup(time(), unserialize(STUDENTGROUPIDS), array(1,2,3));
if (is_array($calendarList)) {
	foreach ($calendarList as $calendar) {
	
		$time = '';
		if($calendar['startTime'] == "0:00" || strtolower($calendar['startTime']) == "12:00am" || strtolower($calendar['startTime']) == "12:00 am" || $calendar['startTime'] == '')
				$time = 'All day';
		else if($calendar['endTime'] == "0:00" || strtolower($calendar['endTime']) == "12:00am" || strtolower($calendar['endTime']) == "12:00 am" || $calendar['endTime'] == '')
				$time = $calendar['startTime'];
		else
				$time = $calendar['startTime']." - ".$calendar['endTime'];
				
		$output = array('ITEM_TITLE'		=> $calendar['note'],
						'ITEM_DESCRIPTION'	=> $time,
						'ITEM_DATE'			=> outputDate('r', $calendar['date']),
						'ITEM_LINK'			=> SITE_address . '/calendar/week/',
						);
		$page->setVar($output);
		$page->parse('rss_item_block', 'rss_item', true);
	}
} else
	$page->setVar('rss_item_block', '');
	
header('Content-type: application/rss+xml');
$page->outputPage(true);

?>