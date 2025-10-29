<?php

/**
 * Controller for resource bookings.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use Alaress\Date\Interval;
use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Alaress\Date\Recurrence;
use Calendar\Event\Type\EventBase;
use Calendar_Api_Config_Mapper;
use DateTime;
use External_Timetable;
use Schoolbox\Calendar;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\TimetableArrayExporter;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception;
use Schoolbox\ResourceBooking\Item;
use Calendar\Event\Type\Timetable\Renderer\Javascript;
use Calendar\Event\Collection\Renderer;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;
use Schoolbox\Calendar\EventRecurrenceMapper;
use Calendar_Api_EventType_ResourceBooking_Booking_ForItem;

class Controller extends Base
{
    /**
     * @var \Schoolbox\ResourceBooking\Booking\Mapper
     */
    protected $bookingMapper;

    /**
     * @var Item\Mapper
     */
    protected $resourceMapper;

    /**
     * @var CampusMapper
     */
    protected $campusMapper;

    /**
     * @var Calendar\EventMapper
     */
    protected $eventMapper;

    /**
     * @var Calendar\EventRecurrenceMapper
     */
    protected $eventRecurrenceMapper;

    /**
     * @var External_Timetable
     */
    protected $externalTimetableHandler;

    /** @var TermMapper */

    private $termMapper;

    /**
     * @var StatusMapper
     */
    protected $statusMapper;


    /**
     * @var \Schoolbox\External\Event\Mapper
     */
    protected \Schoolbox\External\Event\Mapper $externalEventMapper;


    /**
     * Creates a booking for an item.
     *
     * @param Item\Model $item
     *
     * @throws \Exception If cannot parse date interval
     */
    public function book(Item\Model $item)
    {
        $resource = $item;
        $booking  = (new Model())
            ->setResource($resource)
            ->setEvent(new Calendar\Event())
            ->setName($this->requestQuery->get('name'))
            ->setRelatedBookingList(new GenericCollection(Model::class));

        // Create datetime is unique to this method, so it can be a function.
        $createDatetime = function ($date, $time) {
            if ($date && $dateObject = DateTime::createFromFormat('Y-m-d', $date)) {
                if ($time && ($timeObject = DateTime::createFromFormat('H:i', $time))) {
                    $dateObject->setTime($timeObject->format('H'), $timeObject->format('i'));
                } else {
                    $dateObject->modify('+1 hour');
                    $dateObject->setTime($dateObject->format('H'), 0);
                }

                return $dateObject;
            }

            return null;
        };

        // Was the time set here? If so set it into the booking.
        $startDate = $createDatetime(
            $this->requestQuery->get('startDate'),
            $this->requestQuery->get('startTime')
        );
        $endDate   = $createDatetime(
            $this->requestQuery->get('endDate'),
            $this->requestQuery->get('endTime')
        );
        if ($startDate instanceof DateTime) {
            $booking->getEvent()->setStartDateTime(new Alaress_Date_Format($startDate));

            // No end date? Create one based on the minimum reservation time.
            // Use the DateInterval object to adjust datetimes, to make days
            // behave as expected over DST changes.
            if (!($endDate instanceof DateTime)) {
                $endDate = clone $startDate;

                // No min reservation time configured? Default to 30 min.
                $interval     = $resource->getMinReservationTime();
                $dateInterval = (int)$interval->getSecondTotal()
                    ? $interval->getAsDateInterval()
                    : new \DateInterval('PT30M');
                $endDate->add($dateInterval);
            }
        }
        if ($endDate instanceof DateTime) {
            $booking->getEvent()->setEndDateTime(new Alaress_Date_Format($endDate));
        }

        $this->response = $this->responseFromTemplate(
            '@resourceBooking/book.twig',
            [
                /* Confusingly, 'item' refers to a Booking/Model, and a 'resource'
                 * is the Item/Model itself. Trying to rename it snaps a bunch of
                 * other unrelated stuff
                 */
                'resource'          => $item,
                'item'              => $booking,
                'select'            => 'radio',
                'campusList'        => $this->getCampusList(),
                'returnUrl'         => $this->requestQuery->get('returnUrl'),
                'cancelReturnUrl'   => $this->session->getHistory(),
                'allowOverride'     => $booking->getResource()->canUserModerateThisItem($this->getUser()),
                'currentTerm'       => $this->getTermMapper()->findForToday(),
                'validationData'    => [
                                        'start' => Validation::START_PERIOD_ERRORS,
                                        'end' => Validation::END_PERIOD_ERRORS,
                                        ],
                'validationErrors'  => [
                                        'invalid' => Validation::ERROR_INVALID,
                                        'beforeStart' => Validation::ERROR_BEFORE_START,
                                        'inPast' => Validation::ERROR_IN_PAST,
                                        'tooEarly' => Validation::ERROR_TOO_EARLY,
                                        'tooShort' => Validation::ERROR_TOO_SHORT,
                                        'tooLong' => Validation::ERROR_TOO_LONG,
                                        'conflict' => Validation::ERROR_CONFLICT,
                                        'timetabledClass' => Validation::ERROR_TIMETABLED_CLASS,
                                        'currentTerm' => Validation::ERROR_CURRENT_TERM,
                                        ],
                'recurrence'        => [
                                        Recurrence::RECURRENCE_DAILY => 'Daily',
                                        Recurrence::RECURRENCE_WEEKLY => 'Weekly',
                                        Recurrence::RECURRENCE_FORTNIGHTLY => 'Fortnightly',
                                        Recurrence::RECURRENCE_MONTHLY => 'Monthly',
                                        Recurrence::RECURRENCE_TIMETABLE => 'Timetable Based'
                                        ],
                'efficacySelf'      => EventRecurrenceMapper::EFFICACY_SELF,
                'efficacySeries'    => EventRecurrenceMapper::EFFICACY_SERIES,
            ],
        );
    }

    /**
     * Creates a booking for this resource.
     *
     * @param Item\Model $item The resource to create a booking for.
     * @throws Exception
     */
    public function createBooking(Item\Model $item)
    {
        $bookingList = null;

        // Validate this item.
        $code   = 200;
        $validation = new Validation(
            $this->getUser(),
            $this->getBookingMapper(),
            $this->getStatusMapper(),
            $this->getTermMapper(),
            $this->getExternalTimetableHandler(),
            $this->getCampusMapper(),
            $this->getConfigLoader(),
            $this->getExternalEventMapper()
        );
        $errors = $validation->validateBookingFromPostData($item, $this->requestPost, null);

        // If clashes are allowed on the booking, and all validation errors are clash errors...
        $clashBookingStartDatesAsKeys = [];
        if (
            count($errors) > 0 && $item->getAllowClashBookings() &&
            $validation->validationErrorsAreAllClashes($errors)
        ) {
            // Remember the start dates of clash bookings.
            // Note clash errors always use the START_DATETIME key (see Validation->validateForConflicts()).
            foreach ($errors[Validation::START_PERIOD_ERRORS] as $startDate => $errors) {
                $clashBookingStartDatesAsKeys[$startDate] = null;
            }
            // Ignore errors since all were clashes and clashes are allowed.
            $errors = [];
        }

        if (empty($errors)) {
            // Get the event that all bookings are based off of.
            $event = $this->getBaseEvent();
            $event->setStartDateTime(Alaress_Date_Format::newLocal($this->requestPost['startDate']));
            $event->setEndDateTime(Alaress_Date_Format::newLocal($this->requestPost['endDate']));
            $event->setTitle("Booking: " . $item->getName());
            $event->setDetail($this->requestPost['notes']);

            // Build the booking
            $booking = $this->getBookingFromEvent($item, $event);
            $booking
                ->setName($this->requestPost['name'])
                ->setNotes($this->requestPost['notes']);
            $bookingStartDateTime = $booking->getEvent()->getStartDateTime();
            if (
                $bookingStartDateTime !== null &&
                array_key_exists($bookingStartDateTime->getDatabaseDate(), $clashBookingStartDatesAsKeys)
            ) {
                // Set status to pending because this booking has a clash.
                $booking->setStatus($this->getStatusMapper()->createModel(Status\Model::STATUS_PENDING));
            }
            $bookingList = new Collection();
            $bookingList->add($booking);

            // Expand into bookings for recurrence
            if (!empty($this->requestPost['repeat'])) {
                $bookingList = $this->saveBookingsForRecurrences(
                    $bookingList,
                    $this->requestPost['repeat'],
                    Alaress_Date_Format::newLocalShort($this->requestPost['repeat_until'])->getDateTimeObject(),
                    $clashBookingStartDatesAsKeys,
                    $this->requestPost['campus'],
                );
            } else {
                // Just save the event by itself.
                foreach ($bookingList as $booking) {
                    $this->eventMapper->save($booking->getEvent());
                    // Save personal event relations
                    /** @var Calendar\Relation\PersonalMapper $relationMapper */
                    /** @var Calendar\Relation\Personal $relation */
                    $relationMapper = $this->eventMapper->getJoinMapper('personal');
                    $relation       = $relationMapper->createModel();
                    $relation->setEventId($event->getId());
                    $relation->setUserId($event->getOwner()->getId());
                    $relationMapper->save($relation);
                }
            }

            // Expand into related items.
            $relatedItems = $this->getRelatedItemsFromForm();
            if (!empty($relatedItems)) {
                $bookingList = $this->getBookingsForRelatedItems($bookingList, $relatedItems);
            }
            $this->bookingMapper->save($bookingList);
        } else {
            $code = 400;
        }

        /* Append the booking's id to the url, for the calling module to
         * process in whatever way it needs. This allows a decoupling between
         * Resource Booking and any modules that use it.
         *
         * resourcebooking/booking/book/(itemId)?callbackUrl=/eotc/event/{eotcId}/addBooking/{bookingId}
         * (name/startdate/enddate/details)
         */
        $returnUrl = null;

        if ('' !== ($this->requestPost['returnUrl'] ?? '')) {
            $returnUrl = Redirect::decode($this->requestPost['returnUrl']);
        }

        if ($this->isXhrRequest()) {
            $this->response = $this->responseAsJson(
                [
                    'item' => $bookingList,
                    'errors' => $errors,
                    'bookedPendingBecauseClashes' => count($clashBookingStartDatesAsKeys) > 0,
                    'returnUrl' => (string)$returnUrl,
                ],
            )->withStatus($code);
        } else {
            // Redirect back to previous page, or read page if not set.
            // (let's have a better interface than backToTheFuture too)
            $this->response = $this->redirectAfterPost($returnUrl ?? $this->getSession()->getHistory());
        }
    }

    /**
     * Stub for update method
     *
     * @param Model $item
     * @throws Exception
     */
    public function update(Model $item)
    {
        $originalStart = clone $item->getEvent()->getStartDateTime()->getDateTimeObject();
        $originalEnd   = clone $item->getEvent()->getEndDateTime()->getDateTimeObject();

        // Perform validation, but leave this booking out of calculations.
        $code   = 200;
        $validation = new Validation(
            $this->getUser(),
            $this->getBookingMapper(),
            $this->getStatusMapper(),
            $this->getTermMapper(),
            $this->getExternalTimetableHandler(),
            $this->getCampusMapper(),
            $this->getConfigLoader(),
            $this->getExternalEventMapper()
        );
        $itemResource = $item->getResource();
        $errors = $validation->validateBookingFromPostData($itemResource, $this->requestPost, $item);

        // If clashes are allowed on the booking, and all validation errors are clash errors...
        $clashBookingStartDatesAsKeys = [];
        if (
            count($errors) > 0 && $itemResource->getAllowClashBookings() &&
            $validation->validationErrorsAreAllClashes($errors)
        ) {
            // Remember the start dates of clash bookings.
            // Note clash errors always use the START_DATETIME key (see Validation->validateForConflicts()).
            foreach ($errors[Validation::START_PERIOD_ERRORS] as $startDate => $errors) {
                $clashBookingStartDatesAsKeys[$startDate] = null;
            }
            // Ignore errors since all were clashes and clashes are allowed.
            $errors = [];
        }

        if (empty($errors)) {
            $item
                ->setName($this->requestPost['name'])
                ->setNotes($this->requestPost['notes']);
            $bookingSeries = new Series($this->getBookingMapper(), $this->getStatusMapper(), $this->getExternalTimetableHandler());
            $series = $bookingSeries->getUpdatedSeries(
                $item,
                Alaress_Date_Format::newLocal($this->requestPost['startDate'])->getDateTimeObject(),
                Alaress_Date_Format::newLocal($this->requestPost['endDate'])->getDateTimeObject(),
                $this->getRelatedItemsFromForm(),
                $this->requestPost['efficacy'] ?? null,
            );

            // Get the date and time changes from original booking
            $startDelta = Interval::createFromDateTimeDifference(
                $item->getEvent()->getStartDateTime()->getDateTimeObject(),
                $originalStart
            );
            $endDelta   = Interval::createFromDateTimeDifference(
                $item->getEvent()->getEndDateTime()->getDateTimeObject(),
                $originalEnd
            );

            $isTimeChanged = $startDelta->getSecondTotal() !== 0 || $endDelta->getSecondTotal() !== 0;

            // Save the whole series
            $bookingList = new Collection();
            foreach ($series as $booking) {
                $event = $booking->getEvent();
                // Role and campus currently need to be set to something.
                $event->setRoleCollection(new Collection());
                $event->setCampusCollection(new Collection());
                $event->setDetail($booking->getNotes());
                $this->eventMapper->save($event);

                // If item is moderated, already approved and time changed, set item to need approval again.
                $moderationId = $booking->getResource()->getModerationType()->getId();
                $statusId     = $booking->getStatus()->getId();

                $bookingStartDateTime = $booking->getEvent()->getStartDateTime();
                if (
                    $bookingStartDateTime !== null &&
                    array_key_exists($bookingStartDateTime->getDatabaseDate(), $clashBookingStartDatesAsKeys)
                ) {
                    // Set status to pending because this booking has a clash.
                    $booking->setStatus($this->getStatusMapper()->createModel(Status\Model::STATUS_PENDING));
                } else {
                    if (
                        $booking->getResource()->isUserModerator($this->getUser())
                        && (int)$event->getAuthor()->getId() === (int)$this->getUser()->getId()
                    ) {
                        /* If you're a Moderator and can moderate this request,
                         * and you created the booking in the first place,
                         * set it to Approved - otherwise you'll end up approving your own request anyway
                         */
                        $booking->setStatus($this->getStatusMapper()->createModel(Status\Model::STATUS_APPROVED));
                    } elseif (
                        ($moderationId === Item\ModerationType\Model::TYPE_MODERATED || $moderationId === Item\ModerationType\Model::TYPE_MANAGED) &&
                        $statusId === Status\Model::STATUS_APPROVED &&
                        $isTimeChanged
                    ) {
                        $booking->setStatus($this->getStatusMapper()->createModel(Status\Model::STATUS_PENDING));
                    } else {
                        // Otherwise set the status as if we were creating a non-clashing booking.
                        $booking->setStatus($this->getStatusMapper()->createModel($this->getStatusForNonClashBooking($booking)));
                    }
                }

                $bookingList->add($booking);
            }
            $this->bookingMapper->save($bookingList);

            // Now delete deleted series
            $deletedSeries = $bookingSeries->getDeletedSeries(
                $item,
                Alaress_Date_Format::newLocal($this->requestPost['startDate'])->getDateTimeObject(),
                Alaress_Date_Format::newLocal($this->requestPost['endDate'])->getDateTimeObject(),
                $this->getRelatedItemsFromForm(),
                $this->requestPost['efficacy']
            );
            $this->bookingMapper->delete($deletedSeries);
        } else {
            $code = 400;
        }

        /* Append the booking's id to the url, for the calling module to
         * process in whatever way it needs. This allows a decoupling between
         * Resource Booking any any modules that use it.
         *
         * resourcebooking/booking/book/(itemId)?callbackUrl=/eotc/event/{eotcId}/addBooking/{bookingId}
         * (name/startdate/enddate/details)
         */
        $returnUrl = null;

        if ($this->requestPost->get('returnUrl')) {
            $returnUrl = Redirect::decode($this->requestPost->get('returnUrl'));
        }

        if ($this->isXhrRequest()) {
            $this->response = $this->responseAsJson(
                [
                    'item' => $item,
                    'errors' => $errors,
                    'bookedPendingBecauseClashes' => count($clashBookingStartDatesAsKeys) > 0,
                    'returnUrl' => $returnUrl,
                ],
            )->withStatus($code);
        } else {
            // Redirect back to previous page, or read page if not set.
            // (let's have a better interface than backToTheFuture too)
            // Consider storing redirect URL in form or GET?
            $this->response = $this->redirectAfterPost($returnUrl ?? $this->getSession()->getHistory(2));
        }
    }

    /**
     * Expands $bookingList with a booking for each item in $relatedItems.
     *
     * @param Collection|Model[] $bookingList
     * @param array              $relatedItems
     *
     * @return Collection
     */
    protected function getBookingsForRelatedItems(Collection $bookingList, $relatedItems)
    {
        if (empty($relatedItems)) {
            return $bookingList;
        }
        $newList = new Collection();
        foreach ($bookingList as $booking) {
            foreach ($booking->getResource()->getRelatedResourceList() as $relatedResource) {
                if (array_key_exists($relatedResource->getId(), $relatedItems)) {
                    $relatedItemInputs = $relatedItems[$relatedResource->getId()];
                    $relatedBooking    = $this->getBookingFromEvent(
                        $relatedResource,
                        $booking->getEvent()
                    );
                    $relatedBooking
                        ->setName($booking->getName())
                        ->setNotes($relatedItemInputs['notes']);
                    // Add related booking relation in both directions
                    $booking->addRelatedBooking($relatedBooking);
                    $relatedBooking->addRelatedBooking($booking);
                    $newList->add($relatedBooking);
                }
            }
            $newList->add($booking);
        }

        return $newList;
    }

    /**
     * Saves each item in a list of bookings into a set of bookings for related items.
     *
     * @todo make this a mapper method
     *
     * @param Collection|Model[] $bookingList
     * @param array              $relatedItems
     *
     * @return Collection
     */
    protected function saveBookingsForRelatedItems(Collection $bookingList, $relatedItems)
    {
        $newList = $this->getBookingsForRelatedItems($bookingList, $relatedItems);
        $this->bookingMapper->save($newList);

        return $newList;
    }

    /**
     * Saves each item in a list of bookings into a series of bookings.
     *
     * @param Collection|Model[] $bookingList
     * @param string $recurrenceRule
     * @param DateTime $repeatUntil
     * @param array  $clashBookingStartDatesAsKeys array<string, null>
     *               Array containing start dates of bookings that clash with existing bookings.
     *               This function needs to know which bookings clash so those bookings can be saved with 'pending' status.
     *               Note the most frequent recurrence rule is 'daily', so the recurrence will have at most one booking per day.
     *               The dates are stored as keys to enable efficient checks using array_key_exists().
     *               The format of the dates is expected to be YYYY-MM-DD.
     * @param ?int $campusId
     *
     * @return Collection
     * @throws Exception
     * @todo make this a mapper method
     */
    protected function saveBookingsForRecurrences(
        Collection $bookingList,
        string $recurrenceRule,
        DateTime $repeatUntil,
        array $clashBookingStartDatesAsKeys,
        $campusId = null,
    ): Collection {
        $newList = new Collection();
        $campus = null;
        if ($campusId) {
            $campus = $this->getCampusMapper()->findById($campusId);
        }
        foreach ($bookingList as $booking) {
            $event     = $booking->getEvent();
            $eventList = new Collection();
            if ($repeatUntil instanceof DateTime) {
                $recurrence = new Recurrence(new External_Timetable());
                $period = $recurrence->getRecurrencePeriod(
                    $event->getStartDateTime()->getDateTimeObject(),
                    $recurrenceRule,
                    $repeatUntil,
                    $campus
                );
                // dateperiod doesn't implement count(), so check if there's any
                // dates in $period.
                $recurs = false;
                foreach ($period as $blah) {
                    $recurs = true;
                    break;
                }
                if ($recurs) {
                    $eventList = $this->eventRecurrenceMapper->createSequenceFromIterator(
                        $event,
                        $period,
                        new Calendar\EventRelations(
                            Calendar\Event::ACCESS_PERSONAL,
                            [],
                            [],
                            [],
                            false
                        )
                    );
                }
            }

            if (count($eventList)) {
                // Add a booking for each recurring event that was created.
                foreach ($eventList as $event) {
                    $newBooking = clone $booking;
                    $newBooking->setEvent($event);
                    $eventStartDateTime = $event->getStartDateTime();
                    $newBookingStatus = (
                        $eventStartDateTime !== null &&
                        array_key_exists($eventStartDateTime->getDatabaseDate(), $clashBookingStartDatesAsKeys)
                        ? Status\Model::STATUS_PENDING
                        : $this->getStatusForNonClashBooking($booking)
                    );
                    $newBooking->setStatus($this->getStatusMapper()->createModel($newBookingStatus));
                    $newList->add($newBooking);
                }
            } else {
                // Just add the existing booking back to the collection.
                $newList->add($booking);
            }
        }

        return $newList;
    }

    /**
     * Gets selected related items out of the form, along with notes and name.
     *
     * @return array
     */
    protected function getRelatedItemsFromForm()
    {
        $relatedItems = [];
        foreach ($this->requestPost->get('related_item') ?: [] as $relatedItem) {
            if (isset($relatedItem['resource_id'])) {
                $relatedItems[$relatedItem['resource_id']] = $relatedItem;
            }
        }

        return $relatedItems;
    }

    /**
     * Helper for creating events for resource bookings.
     *
     * @return Calendar\Event
     */
    protected function getBaseEvent()
    {
        // Create the booking, which in turn adds an event to the calendar.
        // This action saves a connected event.
        /** @var Calendar\Event $event */
        $event = $this->eventMapper->createModel();
        // Whatever the booking type is.
        // Could probably be left unset so events show on a different event
        // source.
        // Possible Sources:
        // * My bookings (shows in my elements)
        // * Bookings for items I moderate?)
        $event->setType(Calendar\EventTypeMapper::getInstance()->createModel()->setId(1));
        // Owner and author could be separate - as a moderator I could create
        // bookings for other people.
        $event->setOwner($this->getUser());
        $event->setAuthor($this->getUser());
        $event->setAccess(Calendar\Event::ACCESS_PERSONAL);
        // Currently role and campus must be set to empty collections.
        $event->setRoleCollection(new Collection());
        $event->setCampusCollection(new Collection());

        $event->setPublishDateTime(new Alaress_Date_Format(new DateTime()));

        return $event;
    }

    /**
     * Helper for getting a booking from an event.
     *
     * @param  Item\Model     $resource
     * @param  Calendar\Event $event
     *
     * @return Model
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function getBookingFromEvent(Item\Model $resource, Calendar\Event $event)
    {
        $booking = new Model();
        $booking
            ->setEvent($event)
            ->setResource($resource)
            ->setStatus($this->getStatusMapper()->createModel($this->getStatusForNonClashBooking($booking)))
            ->setName($this->requestPost['name'])
            ->setNotes($this->requestPost['notes'])
            ->setRelatedBookingList(new GenericCollection(Model::class));

        return $booking;
    }


    /**
     * Outputs the calendar for this item.
     *
     * @param Item\Model $item
     * @throws \DomainException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Exception
     */
    public function bookingCalendar(Item\Model $item)
    {
        // Output a calendar as JSON
        // For testing, output the calendar for current user first.
        // the find method should receive:
        // Datetime objects?
        $source = new Calendar_Api_EventType_ResourceBooking_Booking_ForItem(
            Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
            $this->getUser(),
            $this->getUser(),
            [$item->getId()]
        );

        $query = $this->requestQuery;

        // When no event types are selected in the modal, query['status'] is not set.
        // We set it to an empty array here so we can handle the case of no event types selected.
        // When no event types are selected, no events should show on the calendar.
        $source->setStatus($query['status'] ?? []);

        $calendar = new Renderer(
            $source,
            (int)$query['start'],
            (int)$query['end'],
            $this->getUser(),
            $this->getAuthorizer(),
            $this->routeGenerator,
            $this->getUser()
        );

        $output = $calendar->renderAll(EventBase::RENDER_FORMAT_JSON);

        // Get timetabled classes for this room, if there are any.
        $roomCode = $item->getRoomCode();

        if (!empty($roomCode)) {
            $roomCodes = [$item->getRoomCode() => $item->getId()];

            /* Load the schedule timetable-specific connectivity and grab its events
             */
            $roomSource = new \Calendar_Api_EventType_SudsTimetable_Room(
                Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                $this->getUser(),
                $this->getUser(),
                \Schoolbox\External\Event\Mapper::getInstance(),
                $roomCodes
            );

            $roomEvents = $roomSource->getEvents($query['start'], $query['end']);

            foreach ($roomEvents as $event) {
                $renderer = new \Calendar\Event\Type\SudsTimetable\Renderer\Javascript(
                    $this->getAuthorizer(),
                    $this->routeGenerator,
                    $event,
                    $this->getUser(),
                    $this->getUser()
                );
                $output[] = $renderer->render();
            }

            /* Combine it with the cyclic timetable's data too
             */
            $roomSource = new \Calendar_Api_EventType_Timetable_Room(
                Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                $this->getUser(),
                $this->getUser(),
                $this->externalTimetableHandler,
                $roomCodes
            );

            $roomEvents = $roomSource->getEvents($query['start'], $query['end']);

            foreach ($roomEvents as $event) {
                $renderer = new Javascript(
                    $this->getAuthorizer(),
                    $this->routeGenerator,
                    $event,
                    $this->getUser(),
                    $this->getUser()
                );
                $output[] = $renderer->render();
            }
        }

        $output = json_decode('[' . \implode(',', $output) . ']');

        $this->response = $this->responseAsJson($output);
    }

    /**
     * Stub for edit action.
     *
     * @param Model $item
     */
    public function edit(Model $item)
    {
        $item->setRelatedBookingList($this->getBookingMapper()->findRelatedBookings($item->getId()));
        // Set start and end date
        $event = $item->getEvent();
        // Show event's recurrence
        $recurrence = [];
        if ($event->getSeriesId()) {
            $recurrence = $this->getEventRecurrenceMapper()->getSeriesRecurrence($event);
        }

        // Retrieve resource category details for display in breadcrumb
        $resId    = $item->getResource()->getId();
        $category = ($this->getResourceMapper()->findById($resId)->getCategory());
        $item->getResource()->setCategory($category);

        // Cancel callback URL: point to the last page if it wasn't set.
        $this->response = $this->responseFromTemplate(
            '@resourceBooking/book.twig',
            [
                'item'              => $item,
                'resource'          => $item->getResource(),
                'campusList'        => $this->getCampusList(),
                'returnUrl'         => $this->requestQuery->get('returnUrl'),
                'cancelReturnUrl'   => $this->session->getHistory(),
                'allowOverride'     => $item->getResource()->canUserModerateThisItem($this->getUser()),
                'currentTerm'       => $this->getTermMapper()->findForToday(),
                'validationData'    => [
                    'start' => Validation::START_PERIOD_ERRORS,
                    'end' => Validation::END_PERIOD_ERRORS,
                ],
                'validationErrors'  => [
                    'invalid' => Validation::ERROR_INVALID,
                    'beforeStart' => Validation::ERROR_BEFORE_START,
                    'inPast' => Validation::ERROR_IN_PAST,
                    'tooEarly' => Validation::ERROR_TOO_EARLY,
                    'tooShort' => Validation::ERROR_TOO_SHORT,
                    'tooLong' => Validation::ERROR_TOO_LONG,
                    'conflict' => Validation::ERROR_CONFLICT,
                    'timetabledClass' => Validation::ERROR_TIMETABLED_CLASS,
                    'currentTerm' => Validation::ERROR_CURRENT_TERM,
                ],
                'recurrence'        => $recurrence,
                'efficacySelf'      => EventRecurrenceMapper::EFFICACY_SELF,
                'efficacySeries'    => EventRecurrenceMapper::EFFICACY_SERIES
            ],
        );
    }

    /**
     * Helper method for getting list of campuses and their periods and days.
     *
     * @return array
     */
    protected function getCampusList()
    {
        $listGenerator = new TimetableArrayExporter($this->getExternalTimetableHandler());

        return $listGenerator->export($this->getUser());
    }

    /**
     * Attempts to changes status of booking. Also attempts to change any
     * related bookings, if series efficacy is
     * set.
     *
     * @param Model $booking
     * @param int   $status
     *
     * @return array If not empty, this contains the errors for this change.
     */
    protected function changeStatus(Model $booking, $status)
    {
        $collection  = new Collection();
        $currentUser = $this->getUser();

        $validation = new Validation(
            $this->getUser(),
            $this->getBookingMapper(),
            $this->getStatusMapper(),
            $this->getTermMapper(),
            $this->getExternalTimetableHandler(),
            $this->getCampusMapper(),
            $this->getConfigLoader(),
            $this->getExternalEventMapper()
        );
        // validate this booking
        $booking
            ->setStatus($this->getStatusMapper()->createModel($status))
            ->setReviewer($currentUser);
        $errors = $validation->validateChangeStatus($booking, $status);
        $collection->add($booking);

        // Validate the series (if there is one)
        $series = $this->getSeriesBookings($booking);
        if (count($series)) {
            foreach ($series as $relatedBooking) {
                $relatedBooking
                    ->setStatus($this->getStatusMapper()->createModel($status))
                    ->setReviewer($currentUser);
                $errors = array_merge_recursive(
                    $errors,
                    $validation->validateChangeStatus($relatedBooking, $status)
                );
                $collection->add($relatedBooking);
            }
        }
        if (empty($errors)) {
            $this->getBookingMapper()->save($collection);
        }

        return $errors;
    }

    /**
     * Gets the callback URL out of the URL if it's there, otherwise gets the
     * last history page.
     *
     * @return string
     */
    protected function getStatusChangeCallbackUrl()
    {
        return $this->requestPost->get('returnUrl') ?: $this->getSession()->getHistory();
    }

    /**
     * Marks a booking as approved.
     *
     * @param Model $item The booking to approve.
     */
    public function approve(Model $item)
    {
        $errors = $this->changeStatus($item, Status\Model::STATUS_APPROVED);
        $this->setUpdateStatusResponse($item, $this->getStatusChangeCallbackUrl(), $errors);
    }

    /**
     * Marks a booking as rejected.
     *
     * @param Model $item The booking to reject.
     */
    public function reject(Model $item)
    {
        $errors = $this->changeStatus($item, Status\Model::STATUS_REJECTED);
        $this->setUpdateStatusResponse($item, $this->getStatusChangeCallbackUrl(), $errors);
    }

    /**
     * Marks a booking as pending.
     *
     * @param Model $item The booking to set pending.
     */
    public function setPending(Model $item)
    {
        $errors = $this->changeStatus($item, Status\Model::STATUS_PENDING);
        $this->setUpdateStatusResponse($item, $this->getStatusChangeCallbackUrl(), $errors);
    }

    /**
     * Cancels a booking.
     *
     * For now, it does this by deleting the booking, but could later do this by
     * setting a status.
     *
     * Permissions:
     * * Users may cancel their own bookings
     * * Moderators for an object may cancel anyone's bookings
     *
     * @param Model $item The booking to reject.
     */
    public function cancel(Model $item)
    {
        // Delete:
        // * the booking itself
        // * any related bookings
        // * maybe the booking's event?
        $idProperty    = $this->bookingMapper->getIdentifierProperty();
        $itemsToDelete = $this->bookingMapper->createCollection();
        $itemsToDelete->add($item);
        $itemsToDelete = $itemsToDelete->mergeUnique(
            $this->bookingMapper->findRelatedBookings($item->getId()),
            $idProperty
        );
        $series        = $this->getSeriesBookings($item);
        if (count($series)) {
            $itemsToDelete = $itemsToDelete->mergeUnique($series, $idProperty);
            foreach ($series as $seriesBooking) {
                $itemsToDelete = $itemsToDelete->mergeUnique(
                    $this->bookingMapper->findRelatedBookings($seriesBooking->getId()),
                    $idProperty
                );
            }
        }
        $this->bookingMapper->delete($itemsToDelete);

        $callbackUrl = $this->getStatusChangeCallbackUrl();

        // The callback URL might point to the edit page for this booking.
        // Until I implement redirects in route preloads, I'll have to check for
        // that case here.
        if ($callbackUrl === $this->routeGenerator->generate('resourceBooking.edit', ['id' => $item->getId()])) {
            $callbackUrl = $this->routeGenerator->generate(
                'resourceItem.read',
                ['id' => $item->getResource()->getId()]
            );
        }
        $this->setUpdateStatusResponse($item, $callbackUrl);
    }

    /**
     * Helper method for retrieving a series of bookings, if series booking is
     * enabled.
     *
     * @param  Model $booking
     *
     * @return Collection|Model[]
     */
    protected function getSeriesBookings(Model $booking)
    {
        if (
            $this->requestQuery->get('efficacy') === EventRecurrenceMapper::EFFICACY_SERIES
            || $this->requestPost->get('efficacy') === EventRecurrenceMapper::EFFICACY_SERIES
        ) {
            if ($seriesId = $booking->getEvent()->getSeriesId()) {
                return $this->getBookingMapper()->findSeriesBookings($booking->getId(), $seriesId);
            }
        }

        return new Collection();
    }

    /**
     * Processes a redirect after a booking's status is updated.
     *
     * @param Model  $booking
     * @param string $callbackUrl
     * @param array  $errors If not empty, this contains the errors for this change.
     */
    protected function setUpdateStatusResponse(Model $booking, $callbackUrl, $errors = [])
    {
        if ($this->isXhrRequest()) {
            $this->response = $this->responseAsJson(
                [
                    'item'        => $booking,
                    'callbackUrl' => $callbackUrl,
                    'errors'      => $errors,
                ],
            )->withStatus(empty($errors) ? 200 : 400);
        } else {
            // Is there a previous location to redirect to? if so use that.
            $this->response = $this->redirectAfterPost(
                $callbackUrl ?: $this->getSession()->getHistory()
            );
        }
    }

    /**
     * Return the status to be set for a new booking that does not clash with any existing booking.
     * Note bookings that clash with existing bookings should be given status 'pending'.
     *
     * @param Model $booking
     * @return int
     */
    private function getStatusForNonClashBooking(Model $booking): int
    {
        $resource = $booking->getResource();
        $event = $booking->getEvent();

        // What about the resource? If it's an open resource, confirm the
        // booking immediately.
        if ($resource->getModerationType()->getId() === Item\ModerationType\Model::TYPE_OPEN) {
            return Status\Model::STATUS_APPROVED;
        }

        if (
            $resource->isUserModerator($this->getUser())
            && (int)$event->getAuthor()->getId() === (int)$this->getUser()->getId()
        ) {
            /* If you're a Moderator and can moderate this request,
             * and you created the booking in the first place,
             * set it to Approved - otherwise you'll end up approving your own request anyway
             */
            return Status\Model::STATUS_APPROVED;
        }

        return Status\Model::STATUS_PENDING;
    }

    /**
     * @param  \Schoolbox\ResourceBooking\Booking\Mapper $bookingMapper
     *
     * @return $this
     */
    public function setBookingMapper($bookingMapper)
    {
        $this->bookingMapper = $bookingMapper;

        return $this;
    }

    /**
     * @return \Schoolbox\ResourceBooking\Booking\Mapper
     */
    public function getBookingMapper()
    {
        return $this->bookingMapper;
    }

    /**
     * @param  Calendar\EventMapper $eventMapper
     *
     * @return $this
     */
    public function setEventMapper($eventMapper)
    {
        $this->eventMapper = $eventMapper;

        return $this;
    }

    /**
     * @return Calendar\EventMapper
     */
    public function getEventMapper()
    {
        return $this->eventMapper;
    }

    /**
     * @param  Calendar\EventRecurrenceMapper $eventRecurrenceMapper
     *
     * @return $this
     */
    public function setEventRecurrenceMapper($eventRecurrenceMapper)
    {
        $this->eventRecurrenceMapper = $eventRecurrenceMapper;

        return $this;
    }

    /**
     * @return Calendar\EventRecurrenceMapper
     */
    public function getEventRecurrenceMapper()
    {
        return $this->eventRecurrenceMapper;
    }

    /**
     * @param  Item\Mapper $resourceMapper
     *
     * @return $this
     */
    public function setResourceMapper($resourceMapper)
    {
        $this->resourceMapper = $resourceMapper;

        return $this;
    }

    /**
     * @return Item\Mapper
     */
    public function getResourceMapper()
    {
        return $this->resourceMapper;
    }

    /**
     * @param  External_Timetable $externalTimetableHandler
     *
     * @return $this
     */
    public function setExternalTimetableHandler($externalTimetableHandler)
    {
        $this->externalTimetableHandler = $externalTimetableHandler;

        return $this;
    }

    /**
     * @return External_Timetable
     */
    public function getExternalTimetableHandler()
    {
        return $this->externalTimetableHandler;
    }

    /**
     * @return CampusMapper
     */
    public function getCampusMapper()
    {
        return $this->campusMapper;
    }

    /**
     * @param  CampusMapper $campusMapper
     *
     * @return $this
     */
    public function setCampusMapper($campusMapper)
    {
        $this->campusMapper = $campusMapper;

        return $this;
    }

    /**
     * @return StatusMapper
     */
    public function getStatusMapper()
    {
        return $this->statusMapper;
    }

    /**
     * @param  StatusMapper $statusMapper
     *
     * @return $this
     */
    public function setStatusMapper($statusMapper)
    {
        $this->statusMapper = $statusMapper;

        return $this;
    }

    /**
     * @return TermMapper
     */
    public function getTermMapper()
    {
        return $this->termMapper;
    }

    /**
     * @param  TermMapper $termMapper
     *
     * @return $this
     */
    public function setTermMapper($termMapper)
    {
        $this->termMapper = $termMapper;

        return $this;
    }


    /**
     * @return \Schoolbox\External\Event\Mapper
     */
    public function getExternalEventMapper(): \Schoolbox\External\Event\Mapper
    {
        return $this->externalEventMapper;
    }


    /**
     * @param Mapper $externalEventMapper
     * @return Controller
     */
    public function setExternalEventMapper(\Schoolbox\External\Event\Mapper $externalEventMapper): self
    {
        $this->externalEventMapper = $externalEventMapper;
        return $this;
    }
}
