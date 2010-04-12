<?php

require_once SITE_root . '/library/synergetic-user.php';
require_once SITE_root . '/library/submission.php';
require_once SITE_root . '/library/submissionReturn.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/user.php';
require_once SITE_root . '/library/synergetic-class.php';
require_once SITE_root . '/www/include/involved.php';

$this->setFile(TEMPLATE_content, 'submission/dueWork.html');
$this->setBlock(TEMPLATE_content, 'week', 'week_block');
$this->setBlock('week', 'day', 'day_block');
$this->setBlock('day', 'event', 'event_block');

$this->setBlock(TEMPLATE_content, 'has_due', 'has_due_block');
$this->setBlock('has_due', 'due', 'due_block');
$this->setBlock('due', 'download', 'download_block');

$this->setBlock(TEMPLATE_content, 'has_return', 'has_return_block');
$this->setBlock('has_return', 'return', 'return_block');
$this->setBlock('return', 'download_rt', 'download_rt_block');

$this->setBlock(TEMPLATE_content, 'summary', 'summary_block');
$this->setBlock('summary', 'summary_due', 'summary_due_block');

$this->setBlock(TEMPLATE_content, 'activity', 'activity_block');

$this->setBlock(TEMPLATE_content, 'is_parent', 'is_parent_block');

$this->setVar('has_due_block', '');
$this->setVar('has_return_block', '');
$this->setVar('summary_block', '');
$this->setVar('activity_block', '');
$this->setVar('is_parent_block', '');



$groupHandler = new Group;

$userHandler = new User;

$returnHandler = new SubmissionReturn;

$submissionHandler = new Submission;




/* ideally session children would use the id as the array key as this is ridiculous */
$childList = array();
if (is_array($_SESSION['children'])) {
	foreach ($_SESSION['children'] as $child) {
		$childList[] = $child['id'];
	}
}

// get selected user
$userID = is_array($_SESSION['children']) ? $_SESSION['children'][0]['id'] : $_SESSION['id'];
if (is_numeric($this->arg[1]) && in_array($this->arg[1], $childList))
	$userID = $this->arg[1];
elseif (is_numeric($this->arg[1]) && $groupHandler->isStaff())
	$userID = $this->arg[1];

$user = $userHandler->getByID($userID);

if (!isset($user['uid']))
	error(2);


if (is_array($_SESSION['children'])) {
	
	$this->dropDown('child', $_SESSION['children'], $user['uid'], 'is_parent', array('id' => 'CHILD_ID', 'name' => 'CHILD_NAME'));
	
	$this->parse('is_parent_block', 'is_parent');
}



// get category list
$subjectList = false;
if (SYNERGY_enable) {
	$synUser = new SynergeticUser;
	$subjectList = $synUser->getSubjectList($user['synergy_id'], $user['group_id']);
}
$categoryList = involvedCategoryList($user['uid'], $subjectList, 1);




// get selected date
if (isset($this->arg[0]))
	$selectedDate = strtotime($this->arg[0]);

if ($selectedDate <= 0) 
	$selectedDate = time();



// output general data
$output = array('CURRENT_DATE'	=> outputDate('F Y', $selectedDate),
				'NOT_TODAY'		=> date('dmY', $selectedDate) != date('dmY') ? 'not-today' : '',
				'TODAY_DATE'	=> date('Y-m-d'),
				'NEXT_MONTH'	=> date('Y-m-d', strtotime('+1 month', $selectedDate)),
				'PREV_MONTH'	=> date('Y-m-d', strtotime('-1 month', $selectedDate)),
				'SELECTED_DATE'	=> outputDate('Y-m-d', $selectedDate),
				);
$this->setVar($output);




$selectedMonth 	= date("n", $selectedDate);
$selectedYear 	= date("Y", $selectedDate);

// calculate month layout
$firstDay 		= date("w", mktime(0,0,0,$selectedMonth, 1, $selectedYear));
$numDaysinMonth = date("d", mktime(0, 0, 0, $selectedMonth + 1, 0, $selectedYear));
$numWeeksMonth 	= ceil(($firstDay + $numDaysinMonth)/7);
$totalDays 		= $numWeeksMonth * 7 + (6 - $firstDay);

$startDay = mktime(12, 0, 0, $selectedMonth, 1, $selectedYear) - strtotime("+$firstDay day", 0);



// output calendar
$summary = array();
for ($i = 0; $i < $totalDays; $i++) {
	
	$currentDay = strtotime("+$i day", $startDay);
	
	$day = $i % 7;	
	
	if($i >= $firstDay && $i < $numDaysinMonth+$firstDay)
		$this->setVar('OTHER_MONTH', '');


	$output = array('DATE'		=> date("j", $currentDay),
					'PAST'		=> $i >= $firstDay && $i < $numDaysinMonth+$firstDay ? '' : 'past',
					'ACTIVE'	=> date('dmY', $currentDay) == date('dmY', $selectedDate) ? 'active' : '',
					'TODAY'		=> date('dmY') == date('dmY', $currentDay) ? 'today' : '',
					'RIGHTY'	=> $day > 3 ? 'righty' : '',
					);

	$this->setVar($output);
	
	$eventList = $submissionHandler->getByDate($currentDay, $user['uid'], $categoryList);
	if (is_array($eventList)) {
		foreach ($eventList as $event) {
			
			$due = ( !empty($event['realname']) && $event['late_date'] > time() );
			
			$output = array('EVENT_ID'		=> $event['id'],
							'TIME'			=> outputDate('h:ia', $event['date']),
							'EVENT'			=> scrubText($event['description']),
							'EVENT_PAST' 	=> !$due ? 'fade' : '',
							'EVENT_CAT_ID'	=> $event['category'],
							);
			
			$this->setVar($output);
			$this->parse('event_block', 'event', true);
			
			/* record overview for summary box */
			$summary[$event['category']] = array('name' 	=> $event['categoryName'], 
												 'total' 	=> ++$summary[$event['category']]['total'],
												 'due'		=> $due ? ++$summary[$event['category']]['due'] : $summary[$event['category']]['due'],
												 'code'		=> explode(',', $event['categoryCode']),
												 );
		}
	}
	
	$this->parse('day_block', 'day', true);
	$this->setVar('event_block', '');
	
	if ($day == 6 && $i) {
		$this->parse('week_block', 'week', true);
		$this->setVar('day_block', '');
	}

}


// upcoming due work
$workList = $submissionHandler->getUpcoming($user['uid'], $categoryList);
if (is_array($workList)) {
	foreach ($workList as $work) {
		
		/* display date as time if today */
		$left = $work['date'] - time();
		$date = $left < 86400 && $left > 0 ? '<strong>' . outputDate('g:i a', $work['date']) . '</strong>' : outputDate(DATE_short_no_time, $work['date']);
		
		$output = array('DUE_SUBJECT'	=> scrubText($work['categoryName']),
						'DUE_DESC'		=> scrubText($work['description']),
						'DUE_DATE'		=> $date,
						'FILE_ID'		=> $work['file_id'],
						'BOX_ID'		=> $work['id'],
						);
		
		$this->setVar($output);
		
		/* display file download link */
		$this->setVar('download_block', '');
		if ($work['file_id'] > 0)
			$this->parse('download_block', 'download');
				
		$this->parse('due_block', 'due', true);
	}
	
	$this->parse('has_due_block', 'has_due');
}


// returned work
$returnList = $returnHandler->getByUser($user['uid']);
if (is_array($returnList)) {
	foreach ($returnList as $return) {
		
		$output = array('RETURN_SUBJECT'	=> scrubText($return['categoryName']),
						'RETURN_DESC'		=> scrubText($return['description']),
						'RETURN_MARK'		=> scrubText($return['mark']),
						'RETURN_COMMENT'	=> scrubText($return['comment']),
						'FILE_ID'			=> $return['fid'],
						'BOX_ID'			=> $return['box_id'],
						);
		
		$this->setVar($output);
		
		/* display file download link */
		$this->setVar('download_rt_block', '');
		if ($return['fid'] > 0)
			$this->parse('download_rt_block', 'download_rt');
		
		
		$this->parse('return_block', 'return', true);
	}
	
	$this->parse('has_return_block', 'has_return');
}


// subject summary
foreach ($summary as $categoryID => $category) {
	
	
	/* yes it actually requires THIS MUCH code to get a teachers name. */
	if (SYNERGY_enable) {
		
		$synClass = new SynergeticClass;
		
		$teacherOutput = array();
		$teacherList = array();
		if (is_array($category['code'])) {
			foreach ($category['code'] as $code) {
				$temp = $synClass->getTeacherName($category['code']);
				$teacherList[] = $temp['StaffID'];
			}
		}
		
		if (is_array($teacherList)) {
			foreach ($teacherList as $teacherSynID) {
				$teacher = $userHandler->getBySynergyID($teacherSynID);
				$teacherName = formatName($teacher);	
				$emailAddress = formatEmail($teacher);
				$teacherOutput[] = '<a href="'.$_SESSION['mailhandle']."$emailAddress\">$teacherName</a>";
			}
		}
	}
	
	$output = array('SUMMARY_CAT_ID'	=> $categoryID,
					'SUMMARY_CAT_NAME'	=> scrubText($category['name']),
					'SUMMARY_TOTAL'		=> $category['total'],
					'SUMMARY_DUE'		=> $category['due'],
					'SUMMARY_TEACHER'	=> SYNERGY_enable ? implode(', ', $teacherOutput) : '',
					);	
	
	$this->setVar($output);
	
	$this->setVar('summary_due_block', '');
	if ($category['due'] > 0)
		$this->parse('summary_due_block', 'summary_due');
	
	$this->parse('summary_block', 'summary', true);
}


// activities feed
$activityLog = $submissionHandler->getActivityLog($user['uid']);
if (is_array($activityLog)) {
	foreach ($activityLog as $activity) {
		
		$taskName = $activity['description'];
		if ($activity['fid'])
				$taskName = '<a href="/send.php?id=' . $activity['fid'] . '&save=1">' . $activity['description'] . '</a>';
		
		$categoryName = '<a href="/homepage/' . $activity['category'] . '">' . scrubText($activity['categoryName']) . '</a>';
		
		if ($activity['new'] == 'new') {
			$stringDesc = 'New ' . $activity['typeName'] . ' ' . $taskName . ' added for ' . $categoryName;	
		} 
		elseif ($activity['new'] == 'overdue') {
			$stringDesc = '<strong>' . $taskName . ' for ' . $categoryName . ' is overdue</strong> ';
		} 
		else {
			$stringDesc = $categoryName . ' submissions for ' . scrubText($activity['description']) . ' are now closed ';	
		}	
		
		$output = array('ACTIVITY_DESCRIPTION'	=> $stringDesc,
						'DARK'					=> $j++ % 2 ? 'dark' : '',
						);
		$this->setVar($output);
		$this->parse('activity_block', 'activity', true);
	}
}

// subject drop down
$this->dropDown('subject', $categoryList, '', TEMPLATE_content, array('id' => 'SUBJECT_ID', 'name' => 'SUBJECT_NAME'));

?>