<?php
/**
 * Controller for resource bookings.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;
use Alaress\Web\Controller\Base;
use DateTime;
use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\ResourceBooking\Item;
use EOTC\Calendar;

class Controller extends Base {

    /**
     * @var Mapper
     */
    protected $bookingMapper;

    /**
     * @var Item\Mapper
     */
    protected $resourceMapper;

    /**
     * @var Calendar\EventMapper
     */
    protected $eventMapper;

    /**
     * @var Calendar\EventRecurrenceMapper
     */
    protected $eventRecurrenceMapper;

    /**
     * Creates a booking for an item.
     */
    public function book($id) {
        /** @var Model $item */
        /** @var Item\Model $resource */
        $resource = $this->getResourceMapper()->findById($id);
        $item     = $this->getBookingMapper()->createModel();
        $item
            ->setResource($resource)
            ->setEvent(new Calendar\Event());
        // Was the time set here? If so set it into the item.
        if (($startDate = $this->getRequest()->query['startDate'])) {
            // Convert format of startDate and startTime
            if ($startDate = DateTime::createFromFormat('Y-m-d', $startDate)) {
                // Was a time set? If so, add that time to the start date.
                // Otherwise, default to start of next hour.
                if (($time = $this->getRequest()->query['startTime'])
                    && ($time = DateTime::createFromFormat('H:i', $time))
                ) {
                    $startDate->setTime($time->format('H'), $time->format('i'));
                }
                else {
                    $startDate->modify('+1 hour');
                    $startDate->setTime($startDate->format('H'), 0);
                }

                // Set the start and end date and time
                $item->getEvent()->setStartDate($startDate->format('d/m/Y'));
                $item->getEvent()->setStartTime($startDate->format('H:i'));
                $item->getEvent()->setStartDateTime($startDate);

                // Use the DateInterval object to adjust datetimes, to make days
                // behave as expected over DST changes.
                $endDate = clone $startDate;
                $endDate->add($resource->getMinReservationTime()->getAsDateInterval());
                $item->getEvent()->setEndDate($endDate->format('d/m/Y'));
                $item->getEvent()->setEndTime($endDate->format('H:i'));
                $item->getEvent()->setEndDateTime($endDate);
            }
        }
        $this->getResponse()->content->set(
            array(
                'template' => '@resourceBooking/book.twig',
                'data'     => array(
                    'item' => $item,
                ),
            )
        );
    }

    /**
     * Creates a booking for this resource.
     *
     * @param int $id The resource to create a booking for.
     */
    public function createBooking($id) {
        /** @var Item\Model $resource */
        $resource    = $this->getResourceMapper()->findById($id);
        $bookingList = null;

        // Validate this item.
        $code   = 200;
        $errors = $this->validateBookingFromPostData($resource);
        if (empty($errors)) {
            // Create bookings for each linked item.
            $bookingList       = $this->createBookingFromPostData($resource);
            $relatedSelections = $this->getRequest()->post['related_item'] ?: array();
            if (!empty($relatedSelections)) {
                foreach ($resource->getRelatedResourceList() as $relatedResource) {
                    if (in_array($relatedResource->getId(), $relatedSelections)) {
                        $relatedBookingList = $this->createBookingFromPostData($relatedResource);
                        // Link each related booking to its base item.
                        // (This iterator assumes they're created in date order)
                        $iterator = new \MultipleIterator();
                        $iterator->attachIterator(new \IteratorIterator($bookingList));
                        $iterator->attachIterator(new \IteratorIterator($relatedBookingList));
                        foreach ($iterator as $list) {
                            /** @var Model $booking */
                            /** @var Model $relatedBooking */
                            list($booking, $relatedBooking) = $list;
                            $booking->addRelatedBooking($relatedBooking);
                        }
                    }
                }
                // And save each booking list item.
                foreach ($bookingList as $booking) {
                    if (count($booking->getRelatedBookingList())) {
                        $this->getBookingMapper()->saveRelatedBookings($booking);
                    }
                }
            }
        }
        else {
            $code = 400;
        }

        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->status->setCode($code);
            $this->getResponse()->content->set(
                array(
                    'format' => 'json',
                    'data'   => array(
                        'item'   => $bookingList,
                        'errors' => $errors,
                    ),
                )
            );
        }
        else {
            $this->getResponse()->redirect->afterPost(
                $this->getRouter()->generate('resourceItem.read', array('id' => $id))
            );
        }

        // Create a related booking for any related items that were chosen.

        // and redirect somewhere.
        // Was a JSON request made instead? If so return JSON with:
        // * response code: 200=ok, 400=not ok
        // * item: item if it was successfully created
        // * errors: empty if there are no errors, a list of errors otherwise.
    }

    /**
     * Attempts to validate the creation of a booking from post data.
     *
     * @param  Item\Model $resource
     * @return array
     */
    protected function validateBookingFromPostData(Item\Model $resource) {
        // Check:
        // Beginning and end of booking are valid datetime
        $errors    = array();
        $startDate = null;
        $endDate   = null;
        try {
            $startDate = $this->getDateFromForm('start_datetime');
        }
        catch (\UnexpectedValueException $e) {
            $errors['start_datetime'][] = 'invalid';
        }
        try {
            $endDate = $this->getDateFromForm('end_datetime');
        }
        catch (\UnexpectedValueException $e) {
            $errors['end_datetime'][] = 'invalid';
        }

        // As long as I have startTime, I can check leadtime.
        if (isset($startDate)) {
            // Start date before present gets covered by too_early
            // Start time before earliest possible time
            $earliest = new DateTime();
            $earliest->add($resource->getMinLeadTime()->getAsDateInterval());
            if ($startDate < $earliest) {
                $errors['start_datetime'][] = 'too_early';
            }
        }

        // I need both start and end to check reservation span.
        if (isset($startDate, $endDate)) {
            // Obviously start must be before end (gets covered by too_short too)
            if ($startDate > $endDate) {
                $errors['end_datetime'][] = 'before_start';
            }
            // End date before earliest possible end
            $earliestEnd = clone $startDate;
            $earliestEnd->add($resource->getMinReservationTime()->getAsDateInterval());
            if ($endDate < $earliestEnd) {
                $errors['end_datetime'][] = 'too_short';
            }
            // End date after latest possible end
            $latestEnd = clone $startDate;
            $latestEnd->add($resource->getMaxReservationTime()->getAsDateInterval());
            if ($endDate > $latestEnd) {
                $errors['end_datetime'][] = 'too_long';
            }
            // Check conflicting bookings as well for this item.
            // Only check approved bookings. Wow, the calendar population method
            // can do exactly that!
            // I do however want to allow a booking to start at the same time
            // that the previous booking ended.
            // (We may need to implement a down time between bookings.)
            $conflictingBookings = $this->getBookingMapper()->findForResource(
                $resource->getId(),
                $startDate->getTimestamp() + 1,
                $endDate->getTimestamp() - 1,
                array(Status\Model::STATUS_APPROVED)
            );
            if (count($conflictingBookings)) {
                $errors['start_datetime'][] = 'conflict';
            }
        }

        // Validate for recurrences? Any recurrences should be warnings (and the
        // booking should not be made) instead of anything else?

        // Timetable-based recurrences as well?

        // If there's any related resources: validate for those too.
        // Ensure that the user has the right to book this page
        // (though that's an authentication issue, not a data integrity one)
        return $errors;
    }

    /**
     * Creates a date object out of the datepicker and inputs.
     *
     * @param  string $paramName
     * @return DateTime
     * @throws \UnexpectedValueException if creating the date doesn't work
     */
    protected function getDateFromForm($paramName) {
        $datetime = DateTime::createFromFormat(
            'd/m/Y g:iA',
            sprintf(
                '%s %02d:%02d%s',
                $this->getRequest()->post[$paramName]['date'],
                $this->getRequest()->post[$paramName]['hour'],
                $this->getRequest()->post[$paramName]['minute'],
                $this->getRequest()->post[$paramName]['meridien']
            )
        );
        if (!($datetime instanceof DateTime)) {
            throw new \UnexpectedValueException('Could not build date out of form inputs');
        }
        return $datetime;
    }

    /**
     * Helper for creating bookings out of a resource item and post data.
     *
     * @param  Item\Model $resource
     * @return Collection|Model[] A collection of bookings that were created.
     *                            Only one will be created unless it was a
     *                            recurring booking.
     */
    protected function createBookingFromPostData(Item\Model $resource) {
        // Create the booking, which in turn adds an event to the calendar.
        // This action saves a connected event.
        /** @var Calendar\Event $event */
        $event = $this->getEventMapper()->createModel();
        // Whatever the booking type is.
        // Could probably be left unset so events show on a different event
        // source.
        // Possible Sources:
        // * My bookings (shows in my elements)
        // * Bookings for items I moderate?)
        $event->setType(1);
        // Owner and author could be separate - as a moderator I could create
        // bookings for other people.
        $event->setOwner($this->getUser());
        $event->setAuthor($this->getUser());
        $event->setSid($this->getUser()->getId());
        $event->setAccess(Calendar\EventMapper::ACCESS_PERSONAL);
        // Currently role and campus must be set to empty collections.
        $event->setRoleCollection(new \Schoolbox\Collection());
        $event->setCampusCollection(new \Schoolbox\Collection());

        // Currently I can't setStartDateTime() and have that propogate to
        // startDate and startTime.
        // Event doesn't currently take Alaress_Date_Format objects either.
        $startDate = $this->getDateFromForm('start_datetime');
        $endDate   = $this->getDateFromForm('end_datetime');
        $event->setStartDate($startDate->format('Y-m-d'));
        $event->setStartTime($startDate->format('H:i:s'));
        $event->setEndDate($endDate->format('Y-m-d'));
        $event->setEndTime($endDate->format('H:i:s'));

        // Convert time inputs to datetimes.
        $startTime = DateTime::createFromFormat(
            'g:iA',
            sprintf(
                '%02d:%02d%s',
                $this->getRequest()->post['start_datetime']['hour'],
                $this->getRequest()->post['start_datetime']['minute'],
                $this->getRequest()->post['start_datetime']['meridien']
            )
        );
        if ($startTime instanceof DateTime) {
            $event->setStartTime($startTime->format('H:i'));
        }
        $endTime = DateTime::createFromFormat(
            'g:iA',
            sprintf(
                '%02d:%02d%s',
                $this->getRequest()->post['end_datetime']['hour'],
                $this->getRequest()->post['end_datetime']['minute'],
                $this->getRequest()->post['end_datetime']['meridien']
            )
        );
        if ($endTime instanceof DateTime) {
            $event->setEndTime($endTime->format('H:i'));
        }
        $event->setPublishDateTime(new Alaress_Date_Format(new DateTime()));
        $event->setNote("Booking: ".$resource->getName());
        $event->setDetail($this->getRequest()->post['notes']);

        // I've built the event, now build recurrences if required.
        /** @var Collection|Calendar\Event[] $eventList */
        $eventList      = new Collection();
        $recurrenceRule = $this->getRequest()->post['repeat'];
        switch ($recurrenceRule) {
            case 'weekly':
            case 'monthly':
            case 'daily':
                $this->getEventRecurrenceMapper()->save(
                    $event,
                    'series',
                    $recurrenceRule,
                    new Alaress_Date_Format($this->getRequest()->post['repeat_until'], 'd/m/Y'),
                    new Alaress_Date_Format($startDate),
                    new Alaress_Date_Format($endDate)
                );
                // Create a whole set of bookings based on this event.
                $eventList = $this->getEventMapper()->findByColumn('series_id', $event->getSeriesId());
                break;
            default:
                $this->getEventMapper()->save($event);
                $eventList->add($event);
                break;
        }

        // Build a list of bookings to return
        /** @var Collection|Model[] $bookingList */
        $bookingList = new Collection();
        foreach ($eventList as $event) {
            $booking = new Model();
            $booking
                ->setEvent($event)
                ->setResource($resource)
                ->setStatus(Status\Model::STATUS_PENDING);
            ;
            // What about the resource? If it's an open resource, confirm the
            // booking immediately.
            if ($resource->getModerationType()->getId() == Item\ModerationType\Model::TYPE_OPEN) {
                $booking->setStatus(Status\Model::STATUS_APPROVED);
            }
            $bookingList->add($booking);
        }
        $this->getBookingMapper()->save($bookingList);
        return $bookingList;
    }

    /**
     * Outputs the calendar for this item.
     */
    public function bookingCalendar($id) {
        // Output a calendar as JSON
        // For testing, output the calendar for current user first.
        // the find method should receive:
        // Datetime objects?
        /** @var Model[] $bookings */
        $source = new \Calendar_Api_EventType_ResourceBooking_Booking_ForItem(
            $this->getUser(), array()
        );
        $source
            ->setResourceId($id)
            ->setStatus($this->getRequest()->query['status']);
        $events = $source->getEvents(
            $this->getRequest()->query['start'],
            $this->getRequest()->query['end']
        );

        // Pass event to the resource outputter, and deal with its peculiarities
        // No you do not need a sitepage, go away
        // TODO make Calendar_Event_Collection and Calendar_Event_Type_Abstract implement JsonSerializable
        $renderer = new \Calendar_Event_Type_ResourceBooking_Renderer_Javascript(null);
        ob_start();
        foreach ($events as $event) {
            $renderer->visit($event);
        }
        $output = ob_get_clean();
        $output = json_decode('['.$output.'{}]');

        $this->getResponse()->content->set(
            array(
                'format' => 'json',
                'data'   => $output,
            )
        );
    }

    /**
     * Stub for edit action.
     *
     * @param int $id
     */
    public function edit($id) {
        $item = $this->getBookingMapper()->findById($id);
        // Set start and end date
        $event = $item->getEvent();
        $event->setStartDateTime(
            new DateTime($event->getStartDate().' '.$event->getStartTime())
        );
        $event->setEndDateTime(
            new DateTime($event->getEndDate().' '.$event->getEndTime())
        );
        // Show event's recurrence
        $recurrence = array();
        if ($event->getSeriesId()) {
            $recurrence = $this->getEventRecurrenceMapper()->getSeriesRecurrence($event);
        }
        $this->getResponse()->content->set(
            array(
                'template' => '@resourceBooking/book.twig',
                'data'     => array(
                    'item'       => $item,
                    'recurrence' => $recurrence,
                ),
            )
        );
    }

    /**
     * Stub for update method
     *
     * @param $id
     */
    public function update($id) {
        $this->getResponse()->content->set(
            array(
                'format' => 'json',
                'data'   => array(),
            )
        );
    }

    /**
     * Changes status of booking and any related bookings, if series efficacy is
     * set.
     *
     * @param Model $booking
     * @param int   $status
     */
    protected function changeStatus(Model $booking, $status) {
        $currentUser = $this->getUser();
        $update      = function(Model $booking) use($status, $currentUser) {
            $booking
                ->setStatus($status)
                ->setReviewer($currentUser)
            ;
        };
        $update($booking);
        $this->getBookingMapper()->save($booking);
        $series = $this->getSeriesBookings($booking);
        if (count($series)) {
            foreach ($series as $relatedBooking) {
                $update($relatedBooking);
            }
            $this->getBookingMapper()->save($series);
        }
    }

    /**
     * Marks a booking as approved.
     *
     * @param int $id The ID of the booking to approve.
     */
    public function approve($id) {
        /** @var Model $booking */
        $booking = $this->getBookingMapper()->findById($id);
        $this->changeStatus($booking, Status\Model::STATUS_APPROVED);
        $this->setUpdateStatusResponse($booking);
    }

    /**
     * Marks a booking as rejected.
     *
     * @param int $id The ID of the booking to reject.
     */
    public function reject($id) {
        /** @var Model $booking */
        $booking = $this->getBookingMapper()->findById($id);
        $this->changeStatus($booking, Status\Model::STATUS_REJECTED);
        $this->setUpdateStatusResponse($booking);
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
     * @param int $id The ID of the booking to reject.
     */
    public function cancel($id) {
        /** @var Model $booking */
        $booking = $this->getBookingMapper()->findById($id);
        $this->getBookingMapper()->delete($booking);
        $series = $this->getSeriesBookings($booking);
        if (count($series)) {
            $this->getBookingMapper()->delete($series);
        }
        $this->setUpdateStatusResponse($booking);
    }

    /**
     * Helper method for retrieving a series of bookings.
     *
     * @param  Model $booking
     * @return Collection|Model[]
     */
    protected function getSeriesBookings(Model $booking) {
        if ($this->getRequest()->query['efficacy'] === 'series') {
            if ($seriesId = $booking->getEvent()->getSeriesId()) {
                return $this->getBookingMapper()->findSeriesBookings($booking->getId(), $seriesId);
            }
        }
        return new Collection();
    }

    /**
     * Processes a redirect after a booking's status is updated.
     *
     * @param Model $booking
     */
    protected function setUpdateStatusResponse(Model $booking) {
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->status->setCode(200);
            $this->getResponse()->content->set(
                array(
                    'format' => 'json',
                    'data'   => array('item' => $booking,),
                )
            );
        }
        else {
            $this->getResponse()->redirect->afterPost(
                $this->getRouter()->generate(
                    'resourceItem.read', array('id' => $booking->getResource()->getId())
                )
            );
        }
    }

    /**
     * @param  Mapper $bookingMapper
     * @return $this
     */
    public function setBookingMapper($bookingMapper) {
        $this->bookingMapper = $bookingMapper;
        return $this;
    }

    /**
     * @return Mapper
     */
    public function getBookingMapper() {
        return $this->bookingMapper;
    }

    /**
     * @param  Calendar\EventMapper $eventMapper
     * @return $this
     */
    public function setEventMapper($eventMapper) {
        $this->eventMapper = $eventMapper;
        return $this;
    }

    /**
     * @return Calendar\EventMapper
     */
    public function getEventMapper() {
        return $this->eventMapper;
    }

    /**
     * @param  Calendar\EventRecurrenceMapper $eventRecurrenceMapper
     * @return $this
     */
    public function setEventRecurrenceMapper($eventRecurrenceMapper) {
        $this->eventRecurrenceMapper = $eventRecurrenceMapper;
        return $this;
    }

    /**
     * @return Calendar\EventRecurrenceMapper
     */
    public function getEventRecurrenceMapper() {
        return $this->eventRecurrenceMapper;
    }

    /**
     * @param  Item\Mapper $resourceMapper
     * @return $this
     */
    public function setResourceMapper($resourceMapper) {
        $this->resourceMapper = $resourceMapper;
        return $this;
    }

    /**
     * @return Item\Mapper
     */
    public function getResourceMapper() {
        return $this->resourceMapper;
    }

}
