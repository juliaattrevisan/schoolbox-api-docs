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

$sessionList = $workshopHandler->getSessionList();

/* output booking */
$bookedSession = array();
$booked = array();
$bookingList = $workshopHandler->getBookingList();
if (is_array($bookingList)) {
	foreach ($bookingList as $booking) {
		for ($i = $booking['sessionID']; $i < $booking['sessionID'] + $booking['length']; $i++)
			$bookedSession[$i] = true;

		$booked[] = $booking['workshopID'];

		$endSession = $booking['sessionID'] + $booking['length'] - 1;

		$output = array('BOOKING_ID'			=> $booking['workshopID'],
						'BOOKING_TITLE'			=> Scrub::text($booking['title']),
						'BOOKING_ROOM'			=> Scrub::text($booking['room']),
						'BOOKING_DESCRIPTION'	=> Scrub::text($booking['description']),
						'BOOKING_PRESENTER'		=> Scrub::text($booking['presenter']),
						'SESSION_ID'			=> $booking['sessionID'],
						'BOOKING_START'			=> Scrub::text($booking['start']),
						'BOOKING_END'			=> Scrub::text($sessionList[$endSession - 1]['end']),
						'ON_OFF'				=> ++$j % 2 ? 'dark' : '',
						);
		$this->setVar($output);
		$this->parse('booking_block', 'booking', true);
	}
	$this->parse('has_bookings_block', 'has_bookings');
} else
	$this->setVar('has_bookings_block', '<p>You are not booked for any workshops. Please select a workshop from below.</p>');

$open = true;
if (time() < strtotime("June 5 2009, 7am") || time() > strtotime("June 19 2009  4:30pm"))
	$open = false;

$output = array('OPEN'			=> $open ? 'true' : 'false',
				'REGISTRATIONS'	=> time() > strtotime("June 19 2009  4:30pm") ? 'Registrations are now closed' : 'Registrations open Friday 5 June and close Friday 12 June at 4.30pm',
				);
$this->setVar($output);

/* output rooms */
$roomList = $workshopHandler->getRoomList();
if (is_array($roomList)) {
	foreach ($roomList as $room) {

		$output = array('ROOM_NAME'	=> Scrub::text($room['room']),
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

				} elseif ($open && $workshopHandler->sessionOverlap($workshop['sessionID'], $workshop['length'], $bookedSession)) {
					$availability = '';
					$class[] = 'used';

				} elseif ($open && $workshop['booked'] < 20) {
					$availability = 'Book Now';
					$class[] = 'available';

				} elseif ($open) {
					$availability = 'Full';
					$class[] = 'full';
				}

				switch ($workshop['length']) {
					case 1:
						$length = '30 min workshop';
						break;
					case 2:
						$length = '1 hr workshop';
						break;
					default:
						$length = '2 hr workshop';
						break;
				}

				$output = array('WORKSHOP_ID'			=> $workshop['workshopID'],
								'WORKSHOP_TITLE'		=> Scrub::text($workshop['title']),
								'WORKSHOP_DESCRIPTION'	=> Scrub::text($workshop['description']),
								'WORKSHOP_PRESENTER'	=> "&lt;p &gt;" . $length . "&lt;/p&gt;" . Scrub::text($workshop['presenter']),
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
						'SESSION_START'	=> Scrub::text($session['start']),
						'SESSION_END'	=> Scrub::text($session['end']),
						);

	$this->setVar($output);
	$this->parse('session_block', 'session', true);
}


?>
