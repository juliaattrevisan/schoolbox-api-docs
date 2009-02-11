<?php
require_once '../../conf/conf.php';
require_once SITE_root . '/include/sitePage.php';
require_once SITE_root . '/www/modules/tutorial/library/event.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/report-attendance.html');
$page->setBlock(TEMPLATE_content, 'has_events', 'has_events_block');
$page->setBlock('has_events', 'event_list', 'event_list_block');

$page->setTitle('Tutorial Management');

// check access
if (!$_SESSION['su'])
	error(19);
	
if (!isset($_GET['to']))
	$_GET['to'] = date("d/m/Y");

$from = ausDateToUnix($_GET['from']);
$to = ausDateToUnix($_GET['to']);

$output = array('FROM'		=> outputDate("d/m/Y", $from),
				'TO'		=> outputDate("d/m/Y", $to),
				);
$page->setVar($output);

$eventHandler = new Event;
$eventList = $eventHandler->getUncompleted($from, $to);
if (is_array($eventList)) {
	foreach ($eventList as $event) {
		$output = array('NAME'		=> scrubText($event['name']),
						'CODE'		=> !empty($event['code']) ? scrubText($event['code']) : "N/A",
						'DATE'		=> outputDate(DATE_short_no_time, $event['date']),
						'TUTOR1'	=> scrubText($event['tutorName1']),
						'TUTOR2'	=> scrubText($event['tutorName2']),
						);
		$page->setVar($output);
		$page->parse('event_list_block', 'event_list', true);
	}
	$page->parse('has_events_block', 'has_events');
} else
	$page->setVar('has_events_block', '<p>All attendance completed.</p>');

$page->outputPage();
?>