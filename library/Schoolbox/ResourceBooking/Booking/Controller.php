<?php
/**
 * Controller for resource bookings.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;
use Alaress\Date\Interval;
use Alaress\Web\Controller\Base;
use DateTime;
use Alaress_Date_Format;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\ResourceBooking\Item;
use EOTC\Calendar;
use External_Timetable;
use Schoolbox\CampusMapper;

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
     * @var External_Timetable
     */
    protected $externalTimetableHandler;

    /**
     * @var CampusMapper
     */
    protected $campusMapper;

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

        // Load campus and timetable selectors

        // Populate into a campus list instead.
        $this->getResponse()->content->set(
            array(
                'template' => '@resourceBooking/book.twig',
                'data'     => array(
                    'item'       => $item,
                    'campusList' => $this->getCampusList(),
                ),
            )
        );
    }

    /**
     * Outputs a response for getting the next or previous occurence of a
     * timetable day.
     *
     * Query parameters:
     * * campus:   the ID of the campus to get timetable day for
     * * day:      the day number to get
     * * date:     Date to get next or previous occurence in relation to.
     *             Defaults to current date.
     * * previous: If true, gets previous date - otherwise, gets next date.
     *
     */
    public function dateForTimetableDay() {
        $campus    = $this->request->query['campus'];
        $dayNumber = $this->request->query['day'];
        $date      = strtotime($this->request->query['date']);
        $previous  = $this->request->query['previous'];
        $code      = 200;
        $error     = '';
        if ($date === false) {
            $date = null;
        }
        try {
            $nearestDate = $previous ?
                $this->externalTimetableHandler->getPreviousDateForDayNumber($campus, $dayNumber, $date):
                $this->externalTimetableHandler->getNextDateForDayNumber($campus, $dayNumber, $date);
        }
        catch (\InvalidArgumentException $e) {
            $code  = 400;
            $error = $e->getMessage();
        }
        catch (\RangeException $e) {
            $code  = 400;
            $error = $e->getMessage();
        }

        $data = array();
        if ($error) {
            $data['error'] = $error;
        }
        elseif (isset($nearestDate) && $nearestDate instanceof DateTime) {
            $data['date'] = $nearestDate->format('Y-m-d');
        }
        $this->response->status->setCode($code);
        $this->response->content->set(
            array(
                'format' => 'json',
                'data'   => $data,
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
            // Create the booking (or series of bookings) for the base item.
            $bookingList = $this->createBookingFromPostData($resource);

            // Create the related bookings:
            // one for each combination of related item and booking in series.
            $relatedSelections = $this->getRequest()->post['related_item'] ?: array();
            if (!empty($relatedSelections)) {
                $relatedBookingList = new Collection();
                foreach ($resource->getRelatedResourceList() as $relatedResource) {
                    if (in_array($relatedResource->getId(), $relatedSelections)) {
                        foreach ($bookingList as $booking) {
                            // Related bookings share same event as base booking.
                            $relatedBooking = $this->getBookingFromEvent(
                                $relatedResource,
                                $booking->getEvent()
                            );
                            $relatedBooking->addRelatedBooking($booking);
                            $relatedBookingList->add($relatedBooking);
                        }
                    }
                }
                $this->getBookingMapper()->save($relatedBookingList);
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
            // Redirect back to previous page, or read page if not set.
            // (let's have a better interface than backToTheFuture too)
            $this->getResponse()->redirect->afterPost($this->getSession()->getHistory());
        }
    }

    /**
     * Attempts to validate the creation or update of a booking from post data.
     *
     * @param  Item\Model $resource
     * @param  Model      $existingBooking If provided, this is the booking that
     *                                     is being updated.
     *                                     Leave it out of conflict checks.
     * @return array
     */
    protected function validateBookingFromPostData(Item\Model $resource, Model $existingBooking = null) {
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
            // If updating a booking - Remove the item being updated from the
            // check (because it's being moved)
            if ($existingBooking instanceof Model) {
                $existingId = $existingBooking->getId();
                $conflictingBookings = $conflictingBookings->filter(
                    function($item) use($existingId) {
                        return $item->getId() != $existingId;
                    }
                );
            }
            if (count($conflictingBookings)) {
                $errors['start_datetime'][] = 'conflict';
            }
        }

        // If updating a booking and its timespan hasn't changed:
        // unset all timespan related errors.
        // This allows someone to change its notes.
        if ($existingBooking instanceof Model) {
            $existingStart = new DateTime(
                $existingBooking->getEvent()->getStartDate().' '.
                $existingBooking->getEvent()->getStartTime()
            );
            $existingEnd = new DateTime(
                $existingBooking->getEvent()->getEndDate().' '.
                $existingBooking->getEvent()->getEndTime()
            );
            if ($startDate == $existingStart && $endDate == $existingEnd) {
                unset($errors['start_datetime'], $errors['end_datetime']);
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
     * Helper for creating events for resource bookings.
     *
     * Creates an event based on the resource, start and end date and notes.
     *
     * @param  Item\Model $resource
     * @param  DateTime   $startDate
     * @param  DateTime   $endDate
     * @param  string     $notes
     * @return Calendar\Event
     */
    protected function getEventFromPostData(
        Item\Model $resource,
        DateTime $startDate,
        DateTime $endDate,
        $notes
    ) {
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
        $event->setRoleCollection(new Collection());
        $event->setCampusCollection(new Collection());

        // Currently I can't setStartDateTime() and have that propogate to
        // startDate and startTime.
        $event->setStartDateTime($startDate);
        $event->setStartDate($startDate->format('Y-m-d'));
        $event->setStartTime($startDate->format('H:i:s'));
        $event->setEndDateTime($endDate);
        $event->setEndDate($endDate->format('Y-m-d'));
        $event->setEndTime($endDate->format('H:i:s'));
        $event->setPublishDateTime(new Alaress_Date_Format(new DateTime()));
        $event->setNote("Booking: ".$resource->getName());
        $event->setDetail($notes);
        return $event;
    }

    /**
     * Creates events from the given recurrence rule (if there is one).
     * If there isn't one, it just creates the event.
     *
     * @param  Calendar\Event $event
     * @param  string         $recurrenceRule
     * @param  DateTime       $repeatUntil
     * @param  int            $campusId
     * @return Collection|Calendar\Event[] The events that were created.
     */
    protected function createEventsFromRecurrence(
        Calendar\Event $event,
        $recurrenceRule,
        $repeatUntil,
        $campusId
    ) {
        /** @var Collection|Calendar\Event[] $eventList */
        $eventList = new Collection();
        switch ($recurrenceRule) {
            case 'daily':
            case 'weekly':
            case 'fortnightly':
            case 'monthly':
                if ($repeatUntil instanceof DateTime) {
                    $this->getEventRecurrenceMapper()->save(
                        $event,
                        'series',
                        $recurrenceRule,
                        new Alaress_Date_Format($repeatUntil),
                        new Alaress_Date_Format($event->getStartDateTime()),
                        new Alaress_Date_Format($event->getEndDateTime())
                    );
                    // Create a whole set of bookings based on this event.
                    $eventList = $this->getEventMapper()->findByColumn('series_id', $event->getSeriesId());
                }
                break;
            case 'timetable':
                if ($repeatUntil instanceof DateTime) {
                    $period = new \External_TimetableDatePeriod(
                        $event->getStartDateTime(),
                        $repeatUntil->modify('+1 day'),
                        $this->getExternalTimetableHandler(),
                        $campusId
                    );
                    $this->getEventRecurrenceMapper()->createSequenceFromIterator($event, $period);
                    // Create a whole set of bookings based on this event.
                    $eventList = $this->getEventMapper()->findByColumn('series_id', $event->getSeriesId());
                }
                break;
            default:
                $this->getEventMapper()->save($event);
                $eventList->add($event);
                break;
        }
        return $eventList;
    }

    /**
     * Helper for getting a booking from an event.
     *
     * @param  Item\Model     $resource
     * @param  Calendar\Event $event
     * @return Model
     */
    protected function getBookingFromEvent(Item\Model $resource, Calendar\Event $event) {
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
        return $booking;
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
        $event = $this->getEventFromPostData(
            $resource,
            $this->getDateFromForm('start_datetime'),
            $this->getDateFromForm('end_datetime'),
            $this->getRequest()->post['notes']
        );

        // I've built the event, now build recurrences if required.
        /** @var Collection|Calendar\Event[] $eventList */
        $eventList = $this->createEventsFromRecurrence(
            $event,
            $this->getRequest()->post['repeat'],
            DateTime::createFromFormat('d/m/Y', $this->getRequest()->post['repeat_until']),
            $this->getRequest()->post['timetable']['campus']
        );

        // Build a list of bookings to return
        /** @var Collection|Model[] $bookingList */
        $bookingList = new Collection();
        foreach ($eventList as $event) {
            $bookingList->add($this->getBookingFromEvent($resource, $event));
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
     * Currently event datetimes aren't loaded - this method does that.
     *
     * @param Calendar\Event $event
     */
    protected function loadDateTimesIntoEvent(Calendar\Event $event) {
        $event->setStartDateTime(
            new DateTime($event->getStartDate().' '.$event->getStartTime())
        );
        $event->setEndDateTime(
            new DateTime($event->getEndDate().' '.$event->getEndTime())
        );
    }

    /**
     * Stub for edit action.
     *
     * @param int $id
     */
    public function edit($id) {
        $item = $this->getBookingMapper()->findById($id);
        $item->setRelatedBookingList($this->getBookingMapper()->findRelatedBookings($item->getId()));
        // Set start and end date
        $event = $item->getEvent();
        $this->loadDateTimesIntoEvent($event);
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
                    'campusList' => $this->getCampusList(),
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
        $item = $this->getBookingMapper()->findById($id);

        // Perform validation, but leave this booking out of calculations.
        $errors = $this->validateBookingFromPostData($item->getResource(), $item);

        $code = 200;
        if (empty($errors)) {

            // Have the date and time changed?
            // If so, set the date and time for each event, and invalidate
            // each event.
            $this->loadDateTimesIntoEvent($item->getEvent());
            $startDate  = $this->getDateFromForm('start_datetime');
            $endDate    = $this->getDateFromForm('end_datetime');
            $startDelta = \Alaress\Date\Interval::createFromDateTimeDifference(
                $item->getEvent()->getStartDateTime(), $startDate
            );
            $endDelta = \Alaress\Date\Interval::createFromDateTimeDifference(
                $item->getEvent()->getEndDateTime(), $endDate
            );
            $notes = $this->getRequest()->post['notes'];

            // Update this item.
            $this->updateBookingFromPostData($item, $startDelta, $endDelta, $notes);

            // Has the time changed? If so:
            // * If this is a single item, just set status back to pending.
            // * If this is a series booking, the rule is much more complicated.
            //   Probably best to take each booking in the series, and adjust
            //   its time by whatever the difference is between each.

            // Update all of the items in the series.
            $series = $this->getSeriesBookings($item);
            if (count($series)) {
                // update all of these, too
                foreach ($series as $seriesBooking) {
                    $seriesEvent = $seriesBooking->getEvent();
                    $this->loadDateTimesIntoEvent($seriesEvent);
                    // Don't adjust any items that have already started.
                    // It's too late for that!
                    if ($seriesEvent->getStartDateTime() < new DateTime()) {
                        continue;
                    }
                    $this->updateBookingFromPostData($seriesBooking, $startDelta, $endDelta, $notes);
                }
            }

            // Update related bookings too.
            // If an item was in the list but isn't now, remove bookings for it
            // If it wasn't and is, add it.
            // (It should be enough to add/remove from linked bookings)
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
                        'item'   => $item,
                        'errors' => $errors,
                    ),
                )
            );
        }
        else {
            // Redirect back to previous page, or read page if not set.
            // (let's have a better interface than backToTheFuture too)
            // Consider storing redirect URL in form or GET?
            $this->getResponse()->redirect->afterPost($this->getSession()->getHistory(2));
        }
    }

    /**
     * Helper method for getting list of campuses and their periods and days.
     *
     * @return array
     */
    protected function getCampusList() {
        $campusList = array();
        foreach ($this->campusMapper->getAvailableCampusesForUser($this->getUser()) as $campus) {
            /** @var Campus $campus */
            $campusId    = $campus->getId();
            $campusArray = array(
                'id'         => $campusId,
                'name'       => $campus->getName(),
                'days'       => 0,
                'periodList' => array(),
            );

            $timetable = $this->externalTimetableHandler->getTimetableDefinition($campusId);
            // Somehow parse $timetable into a list of names, start and end, and
            // the next day they occur.
            $campusArray['days'] = $timetable['dayCount'];
            foreach ($timetable['desc'] as $i => $name) {
                $campusArray['periodList'][] = array(
                    'index' => $i,
                    'desc'  => $name,
                    'start' => $timetable['start'][$i],
                    'end'   => $timetable['end'][$i],
                );
            }
            $campusList[$campusId] = $campusArray;
        }
        return $campusList;
    }

    /**
     * Attempts to update a booking based on what was passed through post data.
     *
     * @param Model    $item
     * @param Interval $startDelta
     * @param Interval $endDelta
     * @param string   $notes
     */
    protected function updateBookingFromPostData(
        Model $item,
        Interval $startDelta,
        Interval $endDelta,
        $notes
    ) {
        $this->updateBookingFieldsFromPostData($item, $startDelta, $endDelta, $notes);

        // Related bookings:
        // Item wasn't selected and is now: add booking and relation
        // Item was selected and isn't anymore: cancel booking and remove relation
        // Item was selected and still is: preserve booking relation
        $relatedEventList     = new Collection();
        $insertedBookingList  = new Collection();
        $deletedBookingList   = new Collection();
        $updatedSelections    = array();
        $relatedSelections    = $this->getRequest()->post['related_item'] ?: array();
        $relatedBookingList   = $this->getBookingMapper()->findRelatedBookings($item->getId());
        foreach ($relatedBookingList as $relatedBooking) {
            $relatedResourceId = $relatedBooking->getResource()->getId();
            if (in_array($relatedResourceId, $relatedSelections)) {
                // Is the resource still selected?
                // Make sure it's preserved, and the event is updated.
                $updatedSelections[] = $relatedResourceId;
                $relatedBooking = $this->updateBookingFieldsFromPostData(
                    $relatedBooking,
                    $startDelta,
                    $endDelta,
                    $notes
                );
                $relatedEventList->add($relatedBooking->getEvent());
            }
            else {
                // Remove from the list if it isn't.
                $deletedBookingList->add($relatedBooking);
            }
        }

        // Are there any items that weren't selected, but now are?
        // Add them to the list.
        $insertedSelections = array_diff($relatedSelections, $updatedSelections);
        if (!empty($insertedSelections)) {
            $resource = $this->getResourceMapper()->findById($item->getResource()->getId());
            foreach ($resource->getRelatedResourceList() as $relatedResource) {
                if (in_array($relatedResource->getId(), $insertedSelections)) {
                    // New related booking: uses same event as base booking.
                    $relatedBooking = $this->getBookingFromEvent(
                        $relatedResource,
                        $item->getEvent()
                    );
                    // Ensure that the base and related booking are related.
                    $insertedBookingList->add($relatedBooking);
                }
            }
            // Save the inserted bookings.
            $this->getBookingMapper()->save($insertedBookingList);
        }

        // Apply the changes to the related booking as well.
        $this->getEventMapper()->save($item->getEvent());
        $this->getBookingMapper()->save($item);

        // Save each of the related items and their events
        $this->getEventMapper()->save($relatedEventList);
        $this->getBookingMapper()->save($insertedBookingList);
        $this->getBookingMapper()->delete($deletedBookingList);
    }

    /**
     * Attempts to update a booking based on what was passed through post data.
     *
     * @param  Model    $item
     * @param  Interval $startDelta
     * @param  Interval $endDelta
     * @param  string   $notes
     * @return Model
     */
    protected function updateBookingFieldsFromPostData(
        Model $item,
        Interval $startDelta,
        Interval $endDelta,
        $notes
    ) {
        $isTimeChanged = $startDelta->getSecondTotal() !== 0 || $endDelta->getSecondTotal() !== 0;

        $event = $item->getEvent();
        $this->loadDateTimesIntoEvent($event);

        // Adjust the current item.
        if ($isTimeChanged) {
            $item->setStatus(Status\Model::STATUS_PENDING);
            $newStart = $event->getStartDateTime()->add($startDelta->getAsDateInterval());
            $event->setStartDateTime($newStart);
            $event->setStartDate($newStart->format('Y-m-d'));
            $event->setStartTime($newStart->format('H:i:s'));
            $newEnd = $event->getEndDateTime()->add($endDelta->getAsDateInterval());
            $event->setEndDate($newEnd->format('Y-m-d'));
            $event->setEndTime($newEnd->format('H:i:s'));
            if ($item->getResource()->getModerationType()->getId() == Item\ModerationType\Model::TYPE_OPEN) {
                $item->setStatus(Status\Model::STATUS_APPROVED);
            }
        }
        $event->setDetail($notes);
        // Clear the role and campus collection
        $event->setRoleCollection(new Collection());
        $event->setCampusCollection(new Collection());
        $item->setEvent($event);
        return $item;
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
     * Marks a booking as pending.
     *
     * @param int $id The ID of the booking to set pending.
     */
    public function setPending($id) {
        /** @var Model $booking */
        $booking = $this->getBookingMapper()->findById($id);
        $this->changeStatus($booking, Status\Model::STATUS_PENDING);
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
     * Helper method for retrieving a series of bookings, if series booking is
     * enabled.
     *
     * @param  Model $booking
     * @return Collection|Model[]
     */
    protected function getSeriesBookings(Model $booking) {
        if ($this->getRequest()->query['efficacy'] === 'series'
            || $this->getRequest()->post['efficacy'] === 'series'
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
            // Is there a previous location to redirect to? if so use that.
            $this->getResponse()->redirect->afterPost($this->getSession()->getHistory());
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

    /**
     * @param  External_Timetable $externalTimetableHandler
     * @return $this
     */
    public function setExternalTimetableHandler($externalTimetableHandler) {
        $this->externalTimetableHandler = $externalTimetableHandler;
        return $this;
    }

    /**
     * @return External_Timetable
     */
    public function getExternalTimetableHandler() {
        return $this->externalTimetableHandler;
    }

    /**
     * @param  CampusMapper $campusMapper
     * @return $this
     */
    public function setCampusMapper($campusMapper) {
        $this->campusMapper = $campusMapper;
        return $this;
    }

    /**
     * @return CampusMapper
     */
    public function getCampusMapper() {
        return $this->campusMapper;
    }

}
