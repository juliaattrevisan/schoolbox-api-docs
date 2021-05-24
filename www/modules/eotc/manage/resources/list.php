<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Learning\Assessment\Redirect;

$authenticator = new \EOTC\Authenticator($this);

$event = false;

if ($authenticator->isEOTCCreator($_SESSION['user'])) {
    $eventMapper = \EOTC\EventMapper::getInstance();

    try {
        $event = $eventMapper->findById($this->arg[0]);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(160);
    }

    $groupHandler = new Group();

    $this->setFile(TEMPLATE_content, 'modules/eotc/manage/resources/list.html');
    $this->setBlock(TEMPLATE_content, 'resourcebooking', 'resourcebooking_block');

    $this->setVar(array(
        'EVENT_ID' => $event->getId(),
        'EVENT_NAME' => $event->getTitle(),
        'EVENT_NAME_ENCODED' => urlencode($event->getTitle()),
        'EVENT_START_DATE' => $event->getStartDateTime()->getDatabaseDate(),
        'EVENT_START_TIME' => $event->getStartDateTime()->getUsingFormat('H:i'),
        'EVENT_END_DATE' => $event->getEndDateTime()->getDatabaseDate(),
        'EVENT_END_TIME' => $event->getEndDateTime()->getUsingFormat('H:i'),

        /* This is a postprocessing redirect that creates relations between resource booking
         * and an eotc event, decoupling them from each other in a specific way
         */
        'RETURN_URL_CALLBACK' => Redirect::encode("/modules/eotc/manage/resources/bookingForm/{$event->getId()}"),

        /* This is a redirect to return to the list screen
         */
        'RETURN_URL' => Redirect::encode("/modules/eotc/manage/resources/list/{$event->getId()}"),
    ));

    /* Render a list of existing bookings for this EOTC Event
     */
    $resourceMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
    $resourceBookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();

    if ($event->getMetadata()->getResourceBookings()->count()) {
        foreach ($event->getMetadata()->getResourceBookings() as $currentResourceBooking) {
            /* TODO: Optimise this!
             */
            $resource = $resourceMapper->findById($currentResourceBooking->getResource()->getId());
            $resourceBooking = $resourceBookingMapper->findById($currentResourceBooking->getId());

            $this->setVar(array(
                'RESOURCEBOOKING_ID' => $resourceBooking->getId(),
                'RESOURCEBOOKING_NAME' => $resourceBooking->getName(),
                'RESOURCEBOOKING_START' => $resourceBooking->getEvent()->getStartDateTime()->getShort(),
                'RESOURCEBOOKING_END' => $resourceBooking->getEvent()->getEndDateTime()->getShort(),
                'RESOURCE_ID' => $resource->getId(),
                'RESOURCE_NAME' => $resource->getName(),
            ));

            $this->parse('resourcebooking_block', 'resourcebooking', true);
        }
    } else {
        $this->setVar(array(
            'resourcebooking_block' => ''
        ));
    }
} else {
    error(19);
}
