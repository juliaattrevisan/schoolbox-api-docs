<?php

require_once SITE_root . '/library/user.php';
require_once dirname(__FILE__) . '/library/workshop.php';

$userHandler = new User;
$workshopHandler = new Workshop;

$this->setFile(TEMPLATE_content, 'modules/workshop/index.html');
$this->setTitle('Workshop Bookings');

$this->setBlock(TEMPLATE_content, 'error', 'error_block');
$this->setBlock(TEMPLATE_content, 'has_bookings', 'has_bookings_block');
$this->setBlock('has_bookings', 'booking', 'booking_block');
$this->setBlock(TEMPLATE_content, 'session', 'session_block');
$this->setBlock(TEMPLATE_content, 'room', 'room_block');
$this->setBlock('room', 'workshop', 'workshop_block');

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
$bookedSession = array();
$booked = array();
$bookingList = $workshopHandler->getBookingList();
if (is_array($bookingList)) {
	foreach ($bookingList as $booking) {
		for ($i = $booking['sessionID']; $i < $booking['sessionID'] + $booking['length']; $i++)
			$bookedSession[$i] = true;
			
		$booked[] = $booking['workshopID'];	
		
		$output = array('BOOKING_ID'			=> $booking['workshopID'],
						'BOOKING_TITLE'			=> scrubText($booking['title']),
						'BOOKING_ROOM'			=> scrubText($booking['room']),
						'BOOKING_DESCRIPTION'	=> scrubText($booking['description']),
						'BOOKING_PRESENTER'		=> scrubText($booking['presenter']),
						'SESSION_ID'			=> $booking['sessionID'],
						'BOOKING_START'			=> scrubText($booking['start']),
						'BOOKING_END'			=> scrubText($booking['end']),
						'ON_OFF'				=> ++$j % 2 ? 'dark' : '',
						);
		$this->setVar($output);
		$this->parse('booking_block', 'booking', true);
	}
	$this->parse('has_bookings_block', 'has_bookings');
} else
	$this->setVar('has_bookings_block', '<p>You are not booked for any workshops. Please select a workshop from below.</p>');

/* output rooms */
$roomList = $workshopHandler->getRoomList();
if (is_array($roomList)) {
	foreach ($roomList as $room) {

		$output = array('ROOM_NAME'	=> scrubText($room['room']),
						);
		$this->setVar($output);
		
		/* output workshops */
		$workshopList = $workshopHandler->getByRoom($room['room']);
		$this->setVar('workshop_block', '');
		if (is_array($workshopList)) {
			
			foreach ($workshopList as $workshop) {
				
				if (!in_array($workshop['room'], $roomList)) array_push($roomList, $workshop['room']);
				
				$class = array();
				$availability = '';
				
				if (in_array($workshop['workshopID'], $booked)) {
					$availability = 'Booked';
					$class[] = 'booked';

				} elseif ($workshopHandler->sessionOverlap($workshop['sessionID'], $workshop['length'], $bookedSession)) {
					$availability = '';
					$class[] = 'used';
					
				} elseif ($workshop['booked'] < 20) {
					$availability = 'Book Now';
					$class[] = 'available';
					
				} else {
					$availability = 'Full';
					$class[] = 'full';
				}
				
				
				
				$output = array('WORKSHOP_ID'			=> $workshop['workshopID'],
								'WORKSHOP_TITLE'		=> scrubText($workshop['title']),
								'WORKSHOP_DESCRIPTION'	=> scrubText($workshop['description']),
								'WORKSHOP_PRESENTER'	=> scrubText($workshop['presenter']),
								'WORKSHOP_LENGTH'		=> $workshop['length'],
								'AVAILABILITY'			=> $availability,
								'CLASS'					=> implode(' ', $class),
								);
								
								
				$this->setVar($output);
				$this->parse('workshop_block', 'workshop', true);
			}
		}
		
		$this->parse('room_block', 'room', true);
	}
}

/* output room header */
$this->setVar('session_block', '');
$sessionList = $workshopHandler->getSessionList();

foreach ($sessionList as $session) {

		$output = array('SESSION_ID'	=> $session['sessionID'],
						'SESSION_NAME'	=> 'Session ' . $session['sessionID'],
						'SESSION_START'	=> scrubText($session['start']),
						'SESSION_END'	=> scrubText($session['end']),
						);

	$this->setVar($output);
	$this->parse('session_block', 'session', true);
}


?>