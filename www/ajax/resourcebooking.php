<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$resourceBookingMapper = new \Schoolbox\ResourceBooking\Booking\Mapper();
$allResourceBookings = $resourceBookingMapper->findColumnLike('name', $_GET['query']);

$return = array();

foreach($allResourceBookings as $currentResourceBooking) {
	$return[] = array(
		'id' => $currentResourceBooking->getId(),
		'name' => $currentResourceBooking->getName(),
	);
}

header('Content-type: application/json');
echo json_encode($return);
