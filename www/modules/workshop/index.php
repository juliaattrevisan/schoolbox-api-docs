<?php

require_once SITE_root . '/library/user.php';
require_once dirname(__FILE__) . '/library/workshop.php';

$userHandler = new User;
$workshopHandler = new Workshop;

$this->setFile(TEMPLATE_content, 'modules/workshop/index.html');
$this->setTitle('Workshop Bookings');

$this->setBlock(TEMPLATE_content, 'error', 'error_block');
$this->setBlock(TEMPLATE_content, 'booking', 'booking_block');
$this->setBlock(TEMPLATE_content, 'room_head', 'room_head_block');
$this->setBlock(TEMPLATE_content, 'session', 'session_block');
$this->setBlock('session', 'workshop', 'workshop_block');

$this->setVar('error_block', '');
if ($this->arg[0] == 'unavailable') {
	$this->setVar('ERROR', 'The workshop you have selected is now full. Please select another form the table.');
	$this->parse('error_block', 'error');
} 
elseif ($this->arg[0] == 'already-booked') {
	$this->setVar('ERROR', 'You already have a booking for that timeslot. Please select another from the table.');
	$this->parse('error_block', 'error');
}


/* output booking */
$current = array();
$bookingList = $workshopHandler->getBookingList();
if (is_array($bookingList)) {
	foreach ($bookingList as $booking) {
		for ($i = $booking['sessionID']; $i < $booking['sessionID'] + $booking['length']; $i++)
			$current[$i] = true;
		
		$output = array('BOOKING_ID'			=> $booking['workshopID'],
						'BOOKING_TITLE'			=> scrubText($booking['title']),
						'BOOKING_ROOM'			=> scrubText($booking['room']),
						'BOOKING_DESCRIPTION'	=> scrubText($booking['description']),
						'BOOKING_PRESENTER'		=> scrubText($booking['presenter']),
						);
		$this->setVar($output);
		$this->parse('booking_block', 'booking', true);
	}
} else
	$this->setVar('booking_block', '<p>You are not booked for any workshops. Please select a workshops from below.</p>');


/* output sessions */
$roomList = array();
$sessionList = $workshopHandler->getSessionList();
if (is_array($sessionList)) {
	foreach ($sessionList as $session) {
		$output = array('SESSION_ID'	=> $session['sessionID'],
						'SESSION_NAME'	=> 'Session ' . $session['sessionID'],
						'SESSION_START'	=> scrubText($session['start']),
						'SESSION_END'	=> scrubText($session['end']),
						);
		$this->setVar($output);
		
		/* output workshops */
		$workshopList = $workshopHandler->getBySession($session['sessionID']);
		$this->setVar('workshop_block', '');
		if (is_array($workshopList)) {
			
			foreach ($workshopList as $workshop) {

				if (!in_array($workshop['room'], $roomList)) array_push($roomList, $workshop['room']);
				
				
				$availability = false;
				for ($i = $workshop['sessionID']; $i < $workshop['sessionID'] + $workshop['length']; $i++) {
					if (isset($current[$i]))
						$availability = '';
				}
				
				if ($availability === false)
					$availability = $workshop['booking'] < 20 ? 'open' : 'full';
				
				$output = array('WORKSHOP_ID'			=> $workshop['workshopID'],
								'WORKSHOP_TITLE'		=> scrubText($workshop['title']),
								'WORKSHOP_DESCRIPTION'	=> scrubText($workshop['description']),
								'WORKSHOP_PRESENTER'	=> scrubText($workshop['presenter']),
								'WORKSHOP_LENGTH'		=> $workshop['length'],
								'AVAILABILITY'			=> $availability,
								);
								
				$this->setVar($output);
				$this->parse('workshop_block', 'workshop', true);
			}
		}
		
		$this->parse('session_block', 'session', true);
	}
}

/* output room header */
$this->setVar('room_head_block', '');
foreach ($roomList as $room) {
	$output = array('ROOM_NAME'	=> scrubText($room),
					);
	$this->setVar($output);
	$this->parse('room_head_block', 'room_head', true);
}


?>