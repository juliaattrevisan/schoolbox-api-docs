<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';
$eventHandler = new Event;

$this->setBlock($component['name'], 'tutorial_list', 'tutorial_list_block');

$eventList = $eventHandler->getAll('day');
if (is_array($eventList)) {
	foreach ($eventList as $event) {
		$output = array('TUTORIAL_START'		=> $event['start'],
						'TUTORIAL_FINISH'		=> $event['finish'],
						'TUTORIAL_NAME'			=> scrubText($event['name']),
						'TUTORIAL_LOCATION'		=> scrubText($event['location']),
						'TUTORIAL_CANCELLED'	=> $event['cancelled'] == 'true' ? 'cancelled' : '',
						);
		$this->setVar($output);
		$this->parse('tutorial_list_block', 'tutorial_list', true);
	}
} else
	$this->setVar('tutorial_list_block', 'There are no events assigned to you today.');

$this->parse('POS'.$component['position'], $component['name']);
	
?>