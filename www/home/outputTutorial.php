<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';
$eventHandler = new Event;
$groupHandler = new Group(TUTORIAL_SERVICE);

$this->setBlock($component['name'], 'tutorial_list', 'tutorial_list_block');
$this->setBlock('tutorial_list', 'modify_tutorial', 'modify_tutorial_block');

$eventList = $eventHandler->getAll('day');
if (is_array($eventList)) {
	foreach ($eventList as $event) {
		$output = array('TUTORIAL_ID'			=> $event['eventID'],
						'TUTORIAL_START'		=> $event['start'],
						'TUTORIAL_FINISH'		=> $event['finish'],
						'TUTORIAL_NAME'			=> scrubText($event['name']),
						'TUTORIAL_LOCATION'		=> scrubText($event['location']),
						'TUTORIAL_CANCELLED'	=> $event['cancelled'] == 'true' ? 'cancelled' : '',
						);
		$this->setVar($output);
		
		$this->setVar('modify_tutorial_block', '');
		if ($groupHandler->getPermission(0) >= FULL_ACCESS || 
		($groupHandler->getPermission(0) == WRITE_ACCESS && in_array($_SESSION['id'], array($event['tutorID_1'], $event['tutoriID_2'])))) {
			$this->parse('modify_tutorial_block', 'modify_tutorial');
		}
		
		$this->parse('tutorial_list_block', 'tutorial_list', true);
	}
} else
	$this->setVar('tutorial_list_block', '<li><p>There are no events assigned to you today.</p></li>');

$this->parse('POS'.$component['position'], $component['name']);
	
?>