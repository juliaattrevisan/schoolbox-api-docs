<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once dirname(__FILE__) . '/library/workshop.php';

$page = new SitePage;
$workshopHandler = new Workshop;

if (is_numeric($_GET['book'])) {
	
	
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
elseif (is_numeric($_GET['remove'])) {
	
	$workshop = $workshopHandler->getByID($_GET['remove']);
	if (!isset($workshop['workshopID']))
		error(2);

	$workshopHandler->deleteBooking($workshop['workshopID']);
	localRedir('/modules/workshop');
}

?>