<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$authenticator = new \EOTC\Authenticator();

if ($authenticator->isEOTCCreator($_SESSION['user'])) {
	$eventMapper = new \EOTC\EventMapper();

	try {
		$event = $eventMapper->findById($this->arg[0]);
	}
	catch (\Exception $e) {
		error(160);
	}

	$groupHandler = new Group();

	$this->setFile(TEMPLATE_content, 'modules/eotc/manage/resources/list.html');
	$this->setBlock(TEMPLATE_content, 'resourcebooking', 'resourcebooking_block');

	$this->setVar(array(
		'EVENT_ID' => $event->getId(),
		'EVENT_NAME' => $event->getNote(),
		'EVENT_NAME_ENCODED' => urlencode($event->getNote()),
		'EVENT_START_DATE' => $event->getStartDate(),
		'EVENT_START_TIME' => $event->getStartDateTime()->getUsingFormat('H:i'),
		'EVENT_END_DATE' => $event->getEndDate(),
		'EVENT_END_TIME' => $event->getEndDateTime()->getUsingFormat('H:i'),
	));

	/* Render a list of existing bookings for this EOTC Event
	 */
	$resourceMapper = new \Schoolbox\ResourceBooking\Item\Mapper();
	$resourceBookingMapper = new \Schoolbox\ResourceBooking\Booking\Mapper();

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
			'RESOURCE_NAME' => $resource->getName()
		));

		$this->parse('resourcebooking_block', 'resourcebooking', true);
	}
}
else {
	error(19);
}