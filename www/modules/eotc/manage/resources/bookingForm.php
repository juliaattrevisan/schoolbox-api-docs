<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/* If you are a Creator, continue
 */
$authenticator = new \EOTC\Authenticator($this);

if ($authenticator->isEOTCCreator($_SESSION['user'])) {
    /* Delete a booking and all in its series
     */
    if (($this->arg[0] == 'delete') && is_numeric($this->arg[1])) {
        $bookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
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
                $eventMapper = \EOTC\EventMapper::getInstance();
                $event = $eventMapper->findById($eventId);

                /* Can't use findRelatedBookings as that is deeply coupled
                 * with the event table, which isn't useful to EOTC
                 */
                $bookingCollection = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance()->findByColumn('id', $allIds);

                $event->getMetadata()->setResourceBookings($bookingCollection);

                /* Add a relation between this EOTC's metadata and the
                 * relevant resource booking
                 */
                \EOTC\EventMetadataMapper::getInstance()->createResourceBookings($event->getMetadata());

                $this->localRedir("/modules/eotc/manage/resources/list/{$event->getId()}");
            } catch (\Schoolbox\Mapper\Exception $e) {
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
} else {
    error(19);
}
