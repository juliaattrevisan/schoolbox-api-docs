<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Calendar;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Event\Access;
use Schoolbox\Calendar\Event\Attendance;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\Loader;
use Schoolbox\GenericCollection;
use Schoolbox\ResourceBooking\Booking\Model;
use Schoolbox\ResourceBooking\Booking\Validation;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Booking\Status;
use Schoolbox\UserMapper;
use Calendar\Event\Type\Day\Renderer\Javascript;
use Schoolbox\Permission\Access\Access as FolderAccess;

/** @var SitePage|null $this */
if (!isset($this)) {
    http_response_code(404);
    die('No direct access permitted');
}

$user    = $_SESSION['user'];
$ownerId = $_POST['userId'];
$owner   = UserMapper::getInstance()->findById($ownerId);
$config  = new Loader();

$event = new Event();

if (is_numeric($_POST['type'])) {
    $type = EventTypeMapper::getInstance()->createModel()->setId($_POST['type']);
} else {
    $type = EventTypeMapper::getInstance()->findAll()->first();
}

//trust no one, char length should match the front end validation
if (mb_strlen($_POST['title'] ?? '', 'UTF-8') > 250) {
    throw new Schoolbox\Application\Exception\Exception("Title must not be longer than 250 characters");
}

/* Persist event attendances
 * If event has zero guests, or if guests weren't specified at all, just provide empty array
 * If you can't add attendees because you're a student and the config var is off, just provide empty array
 */
$guests = [];

if ((new Attendance\ValidCanInvite())->isSatisfiedBy($user, $config->get('calendar_student_can_invite'))) {
    $guests = (!isset($_POST['guests']) || '' === (string)$_POST['guests']) ? [] : explode(',', $_POST['guests']);
}

/* as of 26.0 you can now book resources via the calendar modal */
$resourceIds = [];
if ($this->config->get(ConfigMapper::NAME_RESOURCE_BOOKING_ENABLED)) {
    $resourceIds = (!isset($_POST['resources']) || '' === (string)$_POST['resources']) ? [] : explode(',', $_POST['resources']);
}

$event
    ->setOwner($owner)
    ->setAuthor($user)
    ->setAccess(Event::ACCESS_PERSONAL)
    ->setCampusCollection(new Collection())
    ->setRoleCollection(new Collection())
    ->setType($type)
    ->setVariant($_POST['variant'] ?? Event::VARIANT_EVENT)
    ->setTitle($_POST['title'] ?? '')
    ->setLocation($_POST['location'] ?? '')
    ->setAttendance(\count($guests) ? Event\Attendance\Model::LEVEL_INVITE_ONLY : Event\Attendance\Model::LEVEL_NO_ATTENDANCE)
    ->setDetail($_POST['detail'] ?? '')
    ->setPublishDateTime(Alaress_Date_Format::now())
    ->setCompleted(isset($_POST['completed']) ? $_POST['completed'] : false);

/* Give all-day events a time, so that update.php can calculate the minuteDelta appropriately
 * without adding a 12-hour offset
 */
if ($_POST['allDay']) {
    $dateObj = new Alaress_Date_Format($_POST['date'] . ' 00:00', 'd-m-Y H:i');
    $event
        ->setStartDateTime($dateObj)
        ->setEndDateTime($dateObj)
        ->setAllDay(true);
} else {
    $startDate = new Alaress_Date_Format($_POST['date'], 'd-m-Y H:i');
    $endDate   = new Alaress_Date_Format($_POST['date'], 'd-m-Y H:i');
    $endDate->addHours(1);
    $event
        ->setStartDateTime($startDate)
        ->setEndDateTime($endDate)
        ->setAllDay(false);
}

/* Check user has permission to create this event
 */
$eventAccess = new Access(FolderAccess::getInstance());
if ($eventAccess->checkDestination($user, Event::ACCESS_PERSONAL, $user, $owner) == false) {
    error(35);
}

// now create the related resource bookings
$nBookingsCreatedByStatusId = [];
$bookingList = new Collection();
if (!empty($resourceIds)) {
    // spawn a Resource Booking object here and link it to the event

    // augment POST with necessary data for validation (hack the calendar form to look like the resource booking form)
    $postVars = $_POST;
    if ($_POST['allDay']) {
        $postVars['startDate'] = $dateObj->getDatePicker();
        $endDate = clone $dateObj;
        $endDate = $endDate->endOfDay();
        $postVars['endDate']   = $endDate->getDatePicker();
    } else {
        $postVars['startDate'] = $startDate->getDatePicker();
        $postVars['endDate']   = $endDate->getDatePicker();
    }

    $postVars['campus']   = '';

    $statusMapper = Status\Mapper::getInstance();
    $validation = new Validation(
        $this->user,
        \Schoolbox\ResourceBooking\Booking\Mapper::getInstance(),
        $statusMapper,
        \Schoolbox\Assessment\Term\Mapper::getInstance(),
        $this->getDi()->get('externalTimetable'),
        \Schoolbox\CampusMapper::getInstance(),
        $this->config,
        \Schoolbox\External\Event\Mapper::getInstance()
    );

    foreach ($resourceIds as $resourceId) {
        /** @var Item\Model $item */
        $item = Item\Mapper::getInstance()->findById((int)$resourceId);

        // Validate this item.
        try {
            [$errorsStruct, $clashBookingStartDatesAsKeys] = $validation->validateBookingIgnoringClashesIfClashesAllowed($item, $postVars);
            $booking = getBookingFromEvent($item, $event);
            $isClash = count($clashBookingStartDatesAsKeys) > 0;
            $bookingStatusId = $validation->getNewStatusIdForBooking($booking, $isClash, $user);
            $booking->setStatus(getStatusMapper()->createModel($bookingStatusId));
        } catch (\Schoolbox\Mapper\Exception $e) {
            // Some kind of database problem occurred, so skip this booking.
            // The user can infer that a booking was skipped from the counts of created bookings.
            continue;
        }
        $bookingList->add($booking);

        if (!array_key_exists($bookingStatusId, $nBookingsCreatedByStatusId)) {
            $nBookingsCreatedByStatusId[$bookingStatusId] = 0;
        }
        $nBookingsCreatedByStatusId[$bookingStatusId]++;
    }
}

$affectedIds = EventMapper::getInstance()->save($event);

$bookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
$bookingMapper->save($bookingList);

/* Save a Personal Relation
 */
$personalRelation = new \Schoolbox\Calendar\Relation\Personal();
$personalRelation->setEventId($event->getId());
$personalRelation->setUserId($ownerId);

$personalRelationMapper = new \Schoolbox\Calendar\Relation\PersonalMapper();
$personalRelationMapper->save($personalRelation);

(new Attendance\Transactor(Attendance\Mapper::getInstance(), UserMapper::getInstance()))->update($event, $guests);

// manually craft a json response, to contain logs and the generated event below..
$output = [
    'message' => getResponseMessage($nBookingsCreatedByStatusId),
    'event'  => null,
];

// Get event back out of the connector, so I know the rendering is consistent
if (count($affectedIds) == 1) {
    $calendarConfig = Calendar_Api_Config_Mapper::getInstance()->findOneForClass(Calendar_Api_EventType_Event_Schoolbox_Default::class);
    $connector      = new Calendar_Api_EventType_Event_Schoolbox_Default($calendarConfig->getId(), $owner, $user);

    foreach ($connector->getEvent($event->getId()) as $calendarEvent) {
        // Only one event should ever be gotten here, so render that one only
        /** @var \Schoolbox\User $user */
        $user     = $_SESSION['user'];
        $renderer = new Javascript(
            $this->getDi()->get('authorizer'),
            $this->getDi()->get('router.generator'),
            $calendarEvent,
            $user,
            $user
        );
        $output['event'] = json_decode($renderer->render(), true);
        break;
    }
}

echo json_encode($output);

exit(1);

function getStatusMapper()
{
    return \Schoolbox\ResourceBooking\Booking\Status\Mapper::getInstance();
}

/**
 * Helper for getting a booking from an event.
 * Note the bookings status will be left as null.  This should be set using Validation->getNewStatusIdForBooking().
 *
 * @param Item\Model $resource
 * @param Event      $event
 * @return Model
 */
function getBookingFromEvent(Item\Model $resource, Calendar\Event $event): Model
{
    $booking = new Model();
    $booking
        ->setEvent($event)
        ->setResource($resource)
        ->setName($resource->getName())
        ->setNotes($_POST['detail'] ?? '')
        ->setRelatedBookingList(new GenericCollection(Model::class));

    // Set booking start and end dates based on whether it's an all-day event
    if ($event->getAllDay()) {
        $booking->setBookingStartAt($event->getStartDateTime());
        $endDate = clone $event->getStartDateTime();
        $endDate = $endDate?->endOfDay();
        $booking->setBookingEndAt($endDate);
    } else {
        // Since the bookings should have the same start and end dates as the event, set them as null.
        // This way, if the event is moved to another time, the resource bookings will move with it.
        $booking->setBookingStartAt(null);
        $booking->setBookingEndAt(null);
    }

    return $booking;
}

/**
 * @param array<int, int> $nBookingsCreatedByStatusId - The number of bookings created, indexed by bookingStatusId.
 * @return string
 */
function getResponseMessage(array $nBookingsCreatedByStatusId): string
{
    $nApprovedBookings = $nBookingsCreatedByStatusId[Status\Model::STATUS_APPROVED] ?? 0;
    $nPendingBookings = $nBookingsCreatedByStatusId[Status\Model::STATUS_PENDING] ?? 0;
    $nRejectedBookings = $nBookingsCreatedByStatusId[Status\Model::STATUS_REJECTED] ?? 0;
    $nTotalBookings = $nPendingBookings + $nApprovedBookings + $nRejectedBookings;

    if ($nTotalBookings === 0) {
        return 'Created event with no bookings';
    }

    if ($nTotalBookings === 1) {
        $bookingStatus = match ($nTotalBookings) {
            $nApprovedBookings => 'approved',
            $nPendingBookings => 'pending',
            $nRejectedBookings => 'rejected',
        };
        return "Created event with one $bookingStatus booking";
    }

    $messageEndingParts = [
        $nApprovedBookings === 0 ? '' : "$nApprovedBookings approved",
        $nPendingBookings  === 0 ? '' : "$nPendingBookings pending"  ,
        $nRejectedBookings === 0 ? '' : "$nRejectedBookings rejected",
    ];

    $messageEndingParts = array_filter($messageEndingParts, static fn ($messageEndingPart) => $messageEndingPart !== '');

    return "Created event with $nTotalBookings bookings - " . implode(', ', $messageEndingParts);
}
