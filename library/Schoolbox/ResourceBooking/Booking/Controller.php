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
use Alaress_Date_Format;
use DateTime;
use External_Timetable;
use Schoolbox\Calendar;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\ResourceBooking\Item;

class Controller extends Base
{

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
     * Creates a booking for an item.
     *
     * @param Item\Model $item
     */
    public function book(Item\Model $item)
    {
        /** @var Model $item */
        /** @var Item\Model $resource */
        $resource = $item;
        $item     = $this->getBookingMapper()->createModel();
        $item
            ->setResource($resource)
            ->setEvent(new Calendar\Event())
            ->setName($this->getRequest()->query['name']);

        // Create datetime is unique to this method, so it can be a function.
        $createDatetime = function ($date, $time) {
            if ($dateObject = DateTime::createFromFormat('Y-m-d', $date)) {
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
            $this->request->query['startDate'],
            $this->request->query['startTime']
        );
        $endDate   = $createDatetime(
            $this->request->query['endDate'],
            $this->request->query['endTime']
        );
        if ($startDate instanceof DateTime) {
            $item->getEvent()->setStartDateTime(new Alaress_Date_Format($startDate));

            // No end date? Create one based on the minimum reservation time.
            // Use the DateInterval object to adjust datetimes, to make days
            // behave as expected over DST changes.
            if (!($endDate instanceof DateTime)) {
                $endDate = clone $startDate;
                $endDate->add($resource->getMinReservationTime()->getAsDateInterval());
            }
        }
        if ($endDate instanceof DateTime) {
            $item->getEvent()->setEndDateTime(new Alaress_Date_Format($endDate));
        }

        $this->getResponse()->content->set(
            array(
                'template' => '@resourceBooking/book.twig',
                'data'     => array(
                    'item'        => $item,
                    'campusList'  => $this->getCampusList(),
                    'callbackUrl' => $this->getRequest()->query['callbackUrl'],
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
    public function dateForTimetableDay()
    {
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
                $this->externalTimetableHandler->getPreviousDateForDayNumber($campus, $dayNumber, $date) :
                $this->externalTimetableHandler->getNextDateForDayNumber($campus, $dayNumber, $date);
        } catch (\InvalidArgumentException $e) {
            $code  = 400;
            $error = $e->getMessage();
        } catch (\RangeException $e) {
            $code  = 400;
            $error = $e->getMessage();
        }

        $data = array();
        if ($error) {
            $data['error'] = $error;
        } elseif (isset($nearestDate) && $nearestDate instanceof DateTime) {
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
     * @param Item\Model $item The resource to create a booking for.
     */
    public function createBooking(Item\Model $item)
    {
        $bookingList = null;

        // Validate this item.
        $code   = 200;
        $errors = $this->validateBookingFromPostData($item);
        if (empty($errors)) {
            // Get the event that all bookings are based off of.
            $event = $this->getBaseEvent();
            $event->setStartDateTime(new Alaress_Date_Format($this->getDateFromForm('start_datetime')));
            $event->setEndDateTime(new Alaress_Date_Format($this->getDateFromForm('end_datetime')));
            $event->setTitle("Booking: " . $item->getName());
            $event->setDetail($this->getRequest()->post['notes']);

            // Build the booking
            $booking = $this->getBookingFromEvent($item, $event);
            $booking->setName($this->request->post['name']);
            $bookingList = new Collection();
            $bookingList->add($booking);

            // Expand into bookings for recurrence
            if (!empty($this->request->post['repeat'])) {
                $bookingList = $this->saveBookingsForRecurrences(
                    $bookingList,
                    $this->getRequest()->post['repeat'],
                    DateTime::createFromFormat('d/m/Y', $this->getRequest()->post['repeat_until']),
                    $this->getRequest()->post['timetable']['campus']
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
            if (!empty($this->request->post['related_item'])) {
                $bookingList = $this->saveBookingsForRelatedItems(
                    $bookingList,
                    $this->request->post['related_item'] ? : array()
                );
            } else {
                // Just save the booking.
                $this->bookingMapper->save($bookingList);
            }
        } else {
            $code = 400;
        }

        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->status->setCode($code);

            /* Append the booking's id to the url, for the calling module to
             * process in whatever way it needs. This allows a decoupling between
             * Resource Booking any any modules that use it.
             *
             * resourcebooking/booking/book/(itemId)?callbackUrl=/eotc/event/{eotcId}/addBooking/{bookingId} (name/startdate/enddate/details)
             */
            $callbackUrl = '';

            if (strlen($this->getRequest()->post['callbackUrl'])) {
                $callbackUrl = $this->getRequest()->post['callbackUrl'];
            }

            $this->getResponse()->content->set(
                array(
                    'format' => 'json',
                    'data'   => array(
                        'item'        => $bookingList,
                        'errors'      => $errors,
                        'callbackUrl' => $callbackUrl
                    ),
                )
            );
        } else {
            // Redirect back to previous page, or read page if not set.
            // (let's have a better interface than backToTheFuture too)
            $this->getResponse()->redirect->afterPost($this->getSession()->getHistory());
        }
    }

    /**
     * Stub for update method
     *
     * @param Model $item
     */
    public function update(Model $item)
    {
        $bookingList = null;

        // Perform validation, but leave this booking out of calculations.
        $code   = 200;
        $errors = $this->validateBookingFromPostData($item->getResource(), $item);

        if (empty($errors)) {
            // Get the date and time changes.
            // These will be applied to the whole series.
            $startDate     = $this->getDateFromForm('start_datetime');
            $endDate       = $this->getDateFromForm('end_datetime');
            $startDelta    = Interval::createFromDateTimeDifference(
                $item->getEvent()->getStartDateTime()->getDateTimeObject(),
                $startDate
            );
            $endDelta      = Interval::createFromDateTimeDifference(
                $item->getEvent()->getEndDateTime()->getDateTimeObject(),
                $endDate
            );
            $isTimeChanged =
                $startDelta->getSecondTotal() !== 0
                || $endDelta->getSecondTotal() !== 0;

            /** @var Collection|Model[] $series */
            if ($this->request->post['efficacy'] === 'series') {
                // Load whole series
                $series = $this->bookingMapper->findSeriesBookings(
                    $item->getId(),
                    $item->getEvent()->getSeriesId()
                );
            } else {
                // Divorce booking from its series
                $series = new Collection();
                $item->getEvent()->setSeriesId(null);
            }
            $series->add($item);

            // Save the whole series
            $bookingList = new Collection();
            foreach ($series as $booking) {
                $event = $booking->getEvent();
                // Role and campus currently need to be set to something.
                $event->setRoleCollection(new Collection());
                $event->setCampusCollection(new Collection());

                // Adjust the current event, if its time changed.
                // If times changed, the event's status will need to change.
                if ($isTimeChanged) {
                    $newStart = $event->getStartDateTime()->getDateTimeObject()->add(
                        $startDelta->getAsDateInterval()
                    );
                    $event->setStartDateTime(new Alaress_Date_Format($newStart));
                    $newEnd = $event->getEndDateTime()->getDateTimeObject()->add(
                        $endDelta->getAsDateInterval()
                    );
                    $event->setEndDateTime(new Alaress_Date_Format($newEnd));
                }
                $event->setDetail($this->request->post['notes']);
                $this->eventMapper->save($event);

                // Adjust $booking
                $booking->setName($this->request->post['name']);

                // Get all the related bookings for this event at this time.
                $existingRelatedBookings = $this->bookingMapper->findRelatedBookings($booking->getId());
                foreach ($booking->getResource()->getRelatedResourceList() as $relatedResource) {
                    $relatedBooking = null;
                    foreach ($existingRelatedBookings as $existing) {
                        if ($existing->getResource()->getId() == $relatedResource->getId()) {
                            $relatedBooking = $existing;
                            break;
                        }
                    }
                    $inNew = in_array(
                        $relatedResource->getId(),
                        $this->request->post['related_item'] ? : array()
                    );

                    if ($inNew) {
                        if ($relatedBooking instanceof Model) {
                            // update

                            // Has the booking time changed?
                            // That invalidates existing bookings
                            if ($isTimeChanged) {
                                $relatedBooking->setStatus(Status\Model::STATUS_PENDING);
                            }
                        } else {
                            // create
                            $relatedBooking = new Model();
                            $relatedBooking
                                ->setEvent($event)
                                ->setResource($relatedResource)
                                ->setStatus(Status\Model::STATUS_PENDING);
                        }

                        // Open resource: automatically confirm
                        if ($relatedResource->getModerationType()->getId() == Item\ModerationType\Model::TYPE_OPEN) {
                            $relatedBooking->setStatus(Status\Model::STATUS_APPROVED);
                        }

                        // Status:
                        // pending if new, or time was changed
                        $relatedBooking
                            ->setName($this->request->post['name'])
                            ->addRelatedBooking($booking);
                        $booking->addRelatedBooking($relatedBooking);
                        $this->bookingMapper->save($relatedBooking);
                        $bookingList->add($relatedBooking);
                    } elseif ($relatedBooking instanceof Model) {
                        // delete
                        $this->bookingMapper->delete($relatedBooking);
                    }
                }

                $bookingList->add($booking);
            }
            $this->bookingMapper->save($bookingList);
        } else {
            $code = 400;
        }

        /* Append the booking's id to the url, for the calling module to
         * process in whatever way it needs. This allows a decoupling between
         * Resource Booking any any modules that use it.
         *
         * resourcebooking/booking/book/(itemId)?callbackUrl=/eotc/event/{eotcId}/addBooking/{bookingId} (name/startdate/enddate/details)
         */
        $callbackUrl = '';

        if (strlen($this->getRequest()->post['callbackUrl'])) {
            $callbackUrl = $this->getRequest()->post['callbackUrl'];
        }

        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->status->setCode($code);
            $this->getResponse()->content->set(
                array(
                    'format' => 'json',
                    'data'   => array(
                        'item'        => $item,
                        'errors'      => $errors,
                        'callbackUrl' => $callbackUrl
                    ),
                )
            );
        } else {
            // Redirect back to previous page, or read page if not set.
            // (let's have a better interface than backToTheFuture too)
            // Consider storing redirect URL in form or GET?
            $this->getResponse()->redirect->afterPost($this->getSession()->getHistory(2));
        }
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
        if (empty($relatedItems)) {
            return $bookingList;
        }
        $newList = new Collection();
        foreach ($bookingList as $booking) {
            foreach ($booking->getResource()->getRelatedResourceList() as $relatedResource) {
                if (in_array($relatedResource->getId(), $relatedItems)) {
                    $relatedBooking = $this->getBookingFromEvent(
                        $relatedResource,
                        $booking->getEvent()
                    );
                    $relatedBooking->setName($booking->getName());
                    // Add related booking relation in both directions
                    $booking->addRelatedBooking($relatedBooking);
                    $relatedBooking->addRelatedBooking($booking);
                    $newList->add($relatedBooking);
                }
            }
            $newList->add($booking);
        }
        $this->bookingMapper->save($newList);
        return $newList;
    }

    /**
     * Creates a DatePeriod or other traversable list of DateTime objects that
     * corresponds to a list of event recurrences.
     *
     * @param Calendar\Event $event          The event that is to recur.
     * @param string         $recurrenceRule The recurrence rule:
     *                                       "daily", "weekly", "fortnightly",
     *                                       "monthly" or "timetable".
     *                                       Anything else is treated as a
     *                                       single event.
     * @param DateTime       $repeatUntil    The day to expand this event to.
     * @param int            $campus         The campus to use when determining
     *                                       when a timetable day recurs.
     *
     * @return DateTime[]
     */
    protected function getRecurrencePeriod(
        Calendar\Event $event,
        $recurrenceRule,
        DateTime $repeatUntil,
        $campus
    ) {
        switch ($recurrenceRule) {
            case 'daily':
            case 'weekly':
            case 'fortnightly':
            case 'monthly':
                $period = new \DatePeriod(
                    $event->getStartDateTime()->getDateTimeObject(),
                    \DateInterval::createFromDateString('+1 day'),
                    $repeatUntil->modify('+1 day')
                );
                break;
            case 'timetable':
                $period = new \External_TimetableDatePeriod(
                    $event->getStartDateTime()->getDateTimeObject(),
                    $repeatUntil->modify('+1 day'),
                    $this->getExternalTimetableHandler(),
                    $campus
                );
                break;
            default:
                $period = array();
                break;
        }
        return $period;
    }

    /**
     * Saves each item in a list of bookings into a series of bookings.
     *
     * @todo make this a mapper method
     *
     * @param Collection|Model[] $bookingList
     * @param string             $recurrenceRule
     * @param DateTime           $repeatUntil
     * @param int                $campus
     *
     * @return Collection
     */
    protected function saveBookingsForRecurrences(
        Collection $bookingList,
        $recurrenceRule,
        DateTime $repeatUntil,
        $campus
    ) {
        $newList = new Collection();
        foreach ($bookingList as $booking) {
            $event     = $booking->getEvent();
            $eventList = new Collection();
            if ($repeatUntil instanceof DateTime) {
                $period = $this->getRecurrencePeriod(
                    $event,
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
                    $this->eventRecurrenceMapper->createSequenceFromIterator($event, $period);
                    $eventList = $this->eventMapper->findByColumn('series_id', $event->getSeriesId());
                }
            }

            if (count($eventList)) {
                // Add a booking for each recurring event that was created.
                foreach ($eventList as $event) {
                    $newBooking = clone $booking;
                    $newBooking->setEvent($event);
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
     * Attempts to validate the creation or update of a booking from post data.
     *
     * @param  Item\Model $resource
     * @param  Model      $existingBooking If provided, this is the booking that
     *                                     is being updated.
     *                                     Leave it out of conflict checks.
     *
     * @return array
     */
    protected function validateBookingFromPostData(Item\Model $resource, Model $existingBooking = null)
    {
        // Check:
        // Beginning and end of booking are valid datetime
        $errors    = array();
        $startDate = null;
        $endDate   = null;
        try {
            $startDate = $this->getDateFromForm('start_datetime');
        } catch (\UnexpectedValueException $e) {
            $errors['start_datetime'][] = 'invalid';
        }
        try {
            $endDate = $this->getDateFromForm('end_datetime');
        } catch (\UnexpectedValueException $e) {
            $errors['end_datetime'][] = 'invalid';
        }

        // As long as I have startTime, I can check leadtime.
        if (isset($startDate)) {
            // Start date before present gets covered by too_early
            // Start time before earliest possible time
            $now = new DateTime();
            $now->setTimestamp($this->request->server['REQUEST_TIME']);
            if ($startDate < $now) {
                $errors['start_datetime'][] = 'in_past';
            } else {
                $earliest = new DateTime();
                $earliest->add($resource->getMinLeadTime()->getAsDateInterval());
                if ($startDate < $earliest) {
                    $errors['start_datetime'][] = 'too_early';
                }
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
            // End date after latest possible end (Only if there is an actual max time)
            if ($resource->getMaxReservationTime()->getSecondTotal() > 0) {
                $latestEnd = clone $startDate;
                $latestEnd->add($resource->getMaxReservationTime()->getAsDateInterval());
                if ($endDate > $latestEnd) {
                    $errors['end_datetime'][] = 'too_long';
                }
            }
            
//            // Check conflicts in series and for related items.
//            $relatedItems = $this->request->post['related_item'];


            // Check conflicting bookings as well for this item.
            // Only check approved bookings. Wow, the calendar population method
            // can do exactly that!
            // I do however want to allow a booking to start at the same time
            // that the previous booking ended.
            // (We may need to implement a down time between bookings.)
            $errors = array_merge_recursive(
                $errors,
                $this->validateForConflicts($resource, $startDate, $endDate, $existingBooking)
            );
        }

        // If updating a booking and its timespan hasn't changed:
        // unset all timespan related errors.
        // This allows someone to change its notes.
        if ($existingBooking instanceof Model) {
            $existingStart = new DateTime(
                $existingBooking->getEvent()->getStartDate() . ' ' .
                $existingBooking->getEvent()->getStartTime()
            );
            $existingEnd   = new DateTime(
                $existingBooking->getEvent()->getEndDate() . ' ' .
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
     * Validates whether a booking for this resource conflicts with any
     * bookings that already exist.
     * 
     * @param  Item\Model $resource
     * @param  DateTime   $startDate
     * @param  DateTime   $endDate
     * @param  Model      $existingBooking
     * @return array
     */
    protected function validateForConflicts(
        Item\Model $resource,
        DateTime $startDate,
        DateTime $endDate,
        Model $existingBooking = null
    ) {
        $errors = array();

        // Only check approved bookings. Wow, the calendar population method
        // can do exactly that!
        // I do however want to allow a booking to start at the same time
        // that the previous booking ended.
        // (We may need to implement a down time between bookings.)
        /** @var Collection|Model[] $conflictingBookings */
        $conflictingBookings = $this->getBookingMapper()->findForResource(
            $resource->getId(),
            $startDate->getTimestamp() + 1,
            $endDate->getTimestamp() - 1,
            array(Status\Model::STATUS_APPROVED)
        );
        // If updating a booking - Remove the item being updated from the
        // check (because it's being moved)
        if ($existingBooking instanceof Model) {
            $existingId          = $existingBooking->getId();
            $conflictingBookings = $conflictingBookings->filter(
                function ($item) use ($existingId) {
                    /** @var Model $item */
                    return $item->getId() != $existingId;
                }
            );
        }
        if (count($conflictingBookings)) {
            $errors['start_datetime'][] = 'conflict';
        } elseif (strlen($roomCode = $resource->getRoomCode()) > 0) {
            // Check if there's a timetabled class here, too.
            $roomSource = new \Calendar_Api_EventType_Timetable_Room(
                $this->getUser(),
                array()
            );
            $roomSource->setRoomCode($roomCode);
            $roomEvents = $roomSource->getEvents(
                $startDate->getTimestamp(),
                $endDate->getTimestamp()
            );
            if (count($roomEvents)) {
                $errors['start_datetime'][] = 'timetabled_class';
            }
        }

        return $errors;
    }

    /**
     * Creates a date object out of the datepicker and inputs.
     *
     * @param  string $paramName
     *
     * @return DateTime
     * @throws \UnexpectedValueException if creating the date doesn't work
     */
    protected function getDateFromForm($paramName)
    {
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
        $event->setAccess(Calendar\EventMapper::ACCESS_PERSONAL);
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
     */
    protected function getBookingFromEvent(Item\Model $resource, Calendar\Event $event)
    {
        $booking = new Model();
        $booking
            ->setEvent($event)
            ->setResource($resource)
            ->setStatus(Status\Model::STATUS_PENDING)
            ->setName($this->getRequest()->post['name']);
        // What about the resource? If it's an open resource, confirm the
        // booking immediately.
        if ($resource->getModerationType()->getId() == Item\ModerationType\Model::TYPE_OPEN) {
            $booking->setStatus(Status\Model::STATUS_APPROVED);
        }
        return $booking;
    }

    /**
     * Outputs the calendar for this item.
     *
     * @param Item\Model $item
     */
    public function bookingCalendar(Item\Model $item)
    {
        // Output a calendar as JSON
        // For testing, output the calendar for current user first.
        // the find method should receive:
        // Datetime objects?
        /** @var Model[] $bookings */
        $source = new \Calendar_Api_EventType_ResourceBooking_Booking_ForItem(
            $this->getUser(), array()
        );
        $source
            ->setResourceId($item->getId())
            ->setStatus($this->getRequest()->query['status']);
        $events = $source->getEvents(
            $this->getRequest()->query['start'],
            $this->getRequest()->query['end']
        );

        // Pass event to the resource outputter, and deal with its peculiarities
        // TODO make Calendar_Event_Collection and Calendar_Event_Type_Abstract implement JsonSerializable
        $renderer = new \Calendar_Event_Type_ResourceBooking_Renderer_Javascript(
            $this->getDi()->get('sitePage')
        );
        ob_start();
        foreach ($events as $event) {
            $renderer->visit($event);
        }

        // Get timetabled classes for this room, if there are any.
        $roomCode = $item->getRoomCode();
        if (strlen($roomCode)) {
            $roomSource = new \Calendar_Api_EventType_Timetable_Room(
                $this->getUser(),
                array()
            );
            $roomSource->setRoomCode($item->getRoomCode());
            $roomEvents   = $roomSource->getEvents(
                $this->getRequest()->query['start'],
                $this->getRequest()->query['end']
            );
            $roomRenderer = new \Calendar_Event_Type_Day_Renderer_Javascript(
                $this->getDi()->get('sitePage')
            );
            foreach ($roomEvents as $event) {
                $roomRenderer->visit($event);
            }
        }

        $output = ob_get_clean();
        $output = json_decode('[' . $output . '{}]');

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
     * @param Model $item
     */
    public function edit(Model $item)
    {
        $item->setRelatedBookingList($this->getBookingMapper()->findRelatedBookings($item->getId()));
        // Set start and end date
        $event = $item->getEvent();
        // Show event's recurrence
        $recurrence = array();
        if ($event->getSeriesId()) {
            $recurrence = $this->getEventRecurrenceMapper()->getSeriesRecurrence($event);
        }

        // Cancel callback URL: point to the last page if it wasn't set.
        $this->getResponse()->content->set(
            array(
                'template' => '@resourceBooking/book.twig',
                'data'     => array(
                    'item'              => $item,
                    'recurrence'        => $recurrence,
                    'campusList'        => $this->getCampusList(),
                    'cancelCallbackUrl' => $this->session->getHistory(),
                ),
            )
        );
    }

    /**
     * Helper method for getting list of campuses and their periods and days.
     *
     * @return array
     */
    protected function getCampusList()
    {
        $campusList = array();
        foreach ($this->getUser()->getCampusCollection() as $campus) {
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
            // times always 12-hour, so convert to 24-hour time for consistency
            $campusArray['days'] = $timetable['dayCount'];
            foreach ($timetable['desc'] as $i => $name) {
                $campusArray['periodList'][] = array(
                    'index' => $i,
                    'desc'  => $name,
                    'start' => date('H:i', strtotime($timetable['start'][$i])),
                    'end'   => date('H:i', strtotime($timetable['end'][$i])),
                );
            }
            $campusList[$campusId] = $campusArray;
        }
        return $campusList;
    }

    /**
     * Validates the status change for this booking
     *
     * @param Model $booking
     * @param int   $status
     *
     * @return array If not empty, this contains the errors for this change.
     */
    protected function validateChangeStatus(Model $booking, $status)
    {
        switch ($status) {
            case Status\Model::STATUS_APPROVED:
                return $this->validateConflicts(
                    $booking->getResource(),
                    $booking->getEvent()->getStartDateTime()->getDateTimeObject(),
                    $booking->getEvent()->getEndDateTime()->getDateTimeObject(),
                    $booking
                );
            default:
                return array();
        }
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

        // validate this booking
        $booking
            ->setStatus($status)
            ->setReviewer($currentUser);
        $errors = $this->validateChangeStatus($booking, $status);
        $collection->add($booking);

        // Validate the series (if there is one)
        $series = $this->getSeriesBookings($booking);
        if (count($series)) {
            foreach ($series as $relatedBooking) {
                $relatedBooking
                    ->setStatus($status)
                    ->setReviewer($currentUser);
                $errors = array_merge_recursive(
                    $errors,
                    $this->validateChangeStatus($relatedBooking, $status)
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
        return $this->request->post['callbackUrl'] ? : $this->getSession()->getHistory();
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
        $this->getBookingMapper()->delete($item);
        $series = $this->getSeriesBookings($item);
        if (count($series)) {
            $this->getBookingMapper()->delete($series);
        }
        $callbackUrl = $this->getStatusChangeCallbackUrl();

        // The callback URL might point to the edit page for this booking.
        // Until I implement redirects in route preloads, I'll have to check for
        // that case here.
        if ($callbackUrl === $this->router->generate('resourceBooking.edit', array('id' => $item->getId()))) {
            $callbackUrl = $this->router->generate(
                'resourceItem.read',
                array('id' => $item->getResource()->getId())
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
     * @param Model  $booking
     * @param string $callbackUrl
     * @param array  $errors      If not empty, this contains the errors for this change.
     */
    protected function setUpdateStatusResponse(Model $booking, $callbackUrl, $errors = array())
    {
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->status->setCode(empty($errors) ? 200 : 400);
            $this->getResponse()->content->set(
                array(
                    'format' => 'json',
                    'data'   => array(
                        'item'        => $booking,
                        'callbackUrl' => $callbackUrl,
                        'errors'      => $errors,
                    ),
                )
            );
        } else {
            // Is there a previous location to redirect to? if so use that.
            $this->getResponse()->redirect->afterPost(
                $callbackUrl ? : $this->getSession()->getHistory()
            );
        }
    }

    /**
     * @param  Mapper $bookingMapper
     *
     * @return $this
     */
    public function setBookingMapper($bookingMapper)
    {
        $this->bookingMapper = $bookingMapper;
        return $this;
    }

    /**
     * @return Mapper
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
}
