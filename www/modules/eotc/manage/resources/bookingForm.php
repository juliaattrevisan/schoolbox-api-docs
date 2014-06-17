<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* If you are a Creator, continue
 */
$authenticator = new \EOTC\Authenticator();

if ($authenticator->isEOTCCreator($_SESSION['user'])) {

	/* Delete a booking and all in its series
	 */
	if (($this->arg[0] == 'delete') && is_numeric($this->arg[1])) {
		$bookingMapper = new \Schoolbox\ResourceBooking\Booking\Mapper();
		$booking = $bookingMapper->findById($this->arg[1]);

		/* Delete this instance
		 */
		$bookingMapper->delete($booking);

		$this->backToTheFuture(1);
	}
	/* Create a new set of bookings for this event
	 */
	else {
		/* Check they're all numeric
		 */
		$isNumeric = true;
		$allIds = explode(',', $_GET['ids']);

		foreach ($allIds as $currentBookingId) {
			if ((int)$currentBookingId === 0) {
				$isNumeric = false;
			}
		}

		/* If all ids were numeric, process normally
		 */
		if ($isNumeric) {
			$eventId = $this->arg[0];

			try {
				$eventMapper = new \EOTC\EventMapper();
				$event = $eventMapper->findById($eventId);

				/* Can't use findRelatedBookings as that is deeply coupled
				 * with the event table, which isn't useful to EOTC
				 */
				$bookingMapper = new \Schoolbox\ResourceBooking\Booking\Mapper();
				$bookingCollection = $bookingMapper->findByColumn('id', $allIds);

				$event->getMetadata()->setResourceBookings($bookingCollection);

				/* Add a relation between this EOTC's metadata and the
				 * relevant resource booking
				 */
				$eventMetadataMapper = new \EOTC\EventMetadataMapper();
				$eventMetadataMapper->createResourceBookings($event->getMetadata());

				$this->localRedir("/modules/eotc/manage/resources/list/{$event->getId()}");
			}
			catch (\Exception $e) {
				error(160);
			}

			die();
		}
		/* Otherwise die magnanimously
		 */
		else {
			error(2);
		}
	}
}
else {
	error(19);
}
