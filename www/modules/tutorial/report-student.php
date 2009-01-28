<?php
require_once '../../conf/conf.php';
require_once SITE_root . '/include/sitePage.php';
require_once SITE_root . '/www/modules/tutorial/library/event.php';
require_once SITE_root . '/library/user.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/report-student.html');
$page->setBlock(TEMPLATE_content, 'has_events', 'has_events_block');
$page->setBlock('has_events', 'class_list', 'class_list_block');
$page->setBlock('class_list', 'event_list', 'event_list_block');

$page->setTitle('Tutorial Management');

// check access
if (!$_SESSION['su'])
	error(19);

if (!isset($_GET['to']))
	$_GET['to'] = date("d/m/Y");

$from = ausDateToUnix($_GET['from']);
$to = ausDateToUnix($_GET['to']);


$eventHandler = new Event;
$userHandler = new User;

if (isset($_GET['keyword'])) {
	
	$userList = $userHandler->searchUser($_GET['keyword'], 3);
	if (is_array($userList))
		$studentID	= $userList[0]['uid'];
} else
	$studentID = $_GET['studentID'];

$output = array('FROM'		=> outputDate("d/m/Y", $from),
				'TO'		=> outputDate("d/m/Y", $to),
				'STUDENT'	=> 'N/A',
				);
$page->setVar($output);

if (is_numeric($studentID)) {
	
		$eventList = $eventHandler->getByStudent($studentID, $from, $to);
		if (is_array($eventList)) {
			
			$i = 0;
			$total = count($eventList);
			foreach ($eventList as $event) {
				
				$i++;
				if ($event['code'] != $last['code'] && $i > 1) {
					
					$output = array('TUTORIAL'		=> scrubText($last['name']),
									'CODE'			=> scrubText($last['code']),
									'ATTENDANCE'	=> number_format(($totalAttended/($totalAttended+$totalMissed))*100, 2),
									'TOTAL_ATTENDED'	=> number_format($totalAttended, 2),
									'TOTAL_MISSED'		=> number_format($totalMissed, 2),
									);
					$page->setVar($output);
					
					$page->parse('class_list_block', 'class_list', true);
					$page->setVar('event_list_block', '');
					
					$totalAttended = 0;
					$totalMissed = 0;
					
				}
				
				$hoursAttended = $event['hours'];
				$hoursMissed = 0;
				if ($event['present']!='true') {
					$hoursAttended = 0;
					$hoursMissed = $event['hours'];
					
				}
				
				$output = array('DATE'		=> outputDate(DATE_short_no_time, $event['date']),
								'START'		=> $event['start'],
								'FINISH'	=> $event['finish'],
								'PRESENT'	=> $event['present']=='true'?'Present':'<em>Absent</em>',
								'HOURS_ATTENDED'	=> number_format($hoursAttended, 2),
								'HOURS_MISSED'		=> number_format($hoursMissed, 2),
								'STUDENT'	=> scrubText($event['studentName']),
								);
				$page->setVar($output);
				$page->parse('event_list_block', 'event_list', true);
				
				$last = $event;
				$totalAttended += $hoursAttended;
				$totalMissed += $hoursMissed;

			}

			// last class
			$output = array('TUTORIAL'		=> scrubText($last['name']),
							'CODE'			=> scrubText($last['code']),
							'ATTENDANCE'	=> number_format(($totalAttended/($totalAttended+$totalMissed))*100, 2),
							'TOTAL_ATTENDED'	=> number_format($totalAttended, 2),
							'TOTAL_MISSED'		=> number_format($totalMissed, 2),
							);
			$page->setVar($output);			
			$page->parse('class_list_block', 'class_list', true);

		} else
			$page->setVar('class_list_block', '<p>No attendance to display.</p>');	
		
		$page->parse('has_events_block', 'has_events');
} else
	$page->setVar('has_events_block', '<p>Student not found</p>');	




$page->outputPage();
?>