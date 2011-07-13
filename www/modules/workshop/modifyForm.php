<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once dirname(__FILE__) . '/library/workshop.php';

$page = new SitePage;
$workshopHandler = new Workshop;

/* restrict time bookings can be changed */
$open = true;
if (time() < strtotime("June 5 2009, 7am") || time() > strtotime("June 19 2009  4:30pm"))
	$open = false;

if (is_numeric($_GET['book']) && $open) {
	
	
	$workshop = $workshopHandler->getByID($_GET['book']);
	if (!isset($workshop['workshopID']))
		error(2);
	
	/* check availability */	
	if ($workshop['booked'] >= 20)
		localRedir('/modules/workshop/unavailable');
	
	/* check not already booked */
	$bookingList = $workshopHandler->getBookingList();
	if (is_array($bookingList)) {
		foreach ($bookingList as $booking) {
			if ($workshop['sessionID'] >= $booking['sessionID'] && $workshop['sessionID'] <= ($booking['sessionID'] + $booking['length'] - 1))
				localRedir('/modules/workshop/already-booked');
				
		}
	}
	
	/* place booking */
	$workshopHandler->placeBooking($workshop['workshopID']);
	
	localRedir('/modules/workshop');
	
} 
elseif (is_numeric($_GET['remove']) && $open) {
	
	$workshop = $workshopHandler->getByID($_GET['remove']);
	if (!isset($workshop['workshopID']))
		error(2);

	$workshopHandler->deleteBooking($workshop['workshopID']);
	localRedir('/modules/workshop');
}

?>