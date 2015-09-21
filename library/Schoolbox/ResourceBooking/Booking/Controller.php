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
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\External\TimetableArrayExporter;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\ResourceBooking\Item;
use Calendar\Event\Type\Timetable\Renderer\Javascript;
use Calendar\Event\Collection\Renderer;

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
                    'item'          => $item,
                    'select'        => 'radio',
                    'campusList'    => $this->getCampusList(),
                    'callbackUrl'   => $this->getRequest()->query['callbackUrl'],
                    'allowOverride' => $item->getResource()->canUserModerateThisItem($this->getUser())
                ),
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
            $booking
                ->setName($this->request->post['name'])
                ->setNotes($this->request->post['notes'])
            ;
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
            $relatedItems = $this->getRelatedItemsFromForm();
            if (!empty($relatedItems)) {
                $bookingList = $this->getBookingsForRelatedItems($bookingList, $relatedItems);
            }
            $this->bookingMapper->save($bookingList);
        } else {
            $code = 400;
        }

        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->status->setCode($code);

            /* Append the booking's id to the url, for the calling module to
             * process in whatever way it needs. This allows a decoupling between
             * Resource Booking any any modules that use it.
             *
             * resourcebooking/booking/book/(itemId)?callbackUrl=/eotc/event/{eotcId}/addBooking/{bookingId}
             * (name/startdate/enddate/details)
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
            $isTimeChanged = false;
            $item
                ->setName($this->request->post['name'])
                ->setNotes($this->request->post['notes'])
            ;
            $series = $this->getUpdatedSeries(
                $item,
                $this->getDateFromForm('start_datetime'),
                $this->getDateFromForm('end_datetime'),
                $this->getRelatedItemsFromForm(),
                $this->request->post['efficacy'],
                $isTimeChanged
            );

            // Save the whole series
            $bookingList = new Collection();
            foreach ($series as $booking) {
                $event = $booking->getEvent();
                // Role and campus currently need to be set to something.
                $event->setRoleCollection(new Collection());
                $event->setCampusCollection(new Collection());
                $event->setDetail($booking->getNotes());
                $this->eventMapper->save($event);

                // getUpdatedSeries() sets deleted field if the item isn't in
                // the sequence.
                if ($booking->isDeleted()) {
                    $this->bookingMapper->delete($booking);
                } else {
                    $bookingList->add($booking);
                }
            }
            $this->bookingMapper->save($bookingList);
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
                    $relatedBooking = $this->getBookingFromEvent(
                        $relatedResource,
                        $booking->getEvent()
                    );
                    $relatedBooking
                        ->setName($relatedItemInputs['name'])
                        ->setNotes($relatedItemInputs['notes'])
                    ;
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
     * Creates a DatePeriod or other traversable list of DateTime objects that
     * corresponds to a list of event recurrences.
     *
     * @param DateTime $startTime      The event that is to recur.
     * @param string   $recurrenceRule The recurrence rule:
     *                                 "daily", "weekly", "fortnightly",
     *                                 "monthly" or "timetable".
     *                                 Anything else is treated as a single
     *                                 event.
     * @param DateTime $repeatUntil    The day to expand this event to.
     * @param int      $campus         The campus to use when determining when a
     *                                 timetable day recurs.
     *
     * @return DateTime[]
     */
    protected function getRecurrencePeriod(
        DateTime $startTime,
        $recurrenceRule,
        DateTime $repeatUntil,
        $campus
    ) {
        switch ($recurrenceRule) {
            case 'daily':
                $period = new \DatePeriod(
                    $startTime,
                    \DateInterval::createFromDateString('+1 day'),
                    $repeatUntil->modify('+1 day')
                );
                break;
            case 'weekly':
                $period = new \DatePeriod(
                    $startTime,
                    \DateInterval::createFromDateString('+1 week'),
                    $repeatUntil->modify('+1 day')
                );
                break;
            case 'fortnightly':
                $period = new \DatePeriod(
                    $startTime,
                    \DateInterval::createFromDateString('+2 weeks'),
                    $repeatUntil->modify('+1 day')
                );
                break;
            case 'monthly':
                $period = new \DatePeriod(
                    $startTime,
                    \DateInterval::createFromDateString('+1 month'),
                    $repeatUntil->modify('+1 day')
                );
                break;
            case 'timetable':
                try {
                    /** @var Campus $campus */
                    $campus = $this->getCampusMapper()->findById(
                        $this->getRequest()->post['timetable']['campus']
                    );
                    $period = new \External_TimetableDatePeriod(
                        $startTime,
                        $repeatUntil->modify('+1 day'),
                        $this->getExternalTimetableHandler(),
                        $campus
                    );
                } catch (MapperException $e) {
                    $period = array();
                }
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
                            Calendar\EventMapper::ACCESS_PERSONAL,
                            array(),
                            array()
                        )
                    );
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
        // (These checks can't be overridden.)
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

        // Only moderators of this booking may override validation
        $override = !empty($this->getRequest()->post['override_validation'])
            && $resource->canUserModerateThisItem($this->getUser());

        // As long as I have startTime, I can check leadtime.
        if (isset($startDate) && !$override) {
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
            // (This can't be overridden)
            if ($startDate > $endDate) {
                $errors['end_datetime'][] = 'before_start';
            }

            // Am I overriding? Perform no other validation if so
            if (!$override) {
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
                if (!($existingBooking instanceof Model)) {
                    // Validating a new booking?
                    // Expand this single booking out to a list of related items,
                    // and validate them all.
                    $dateParts = array(
                        array($resource, $startDate, $endDate),
                    );

                    // Check each in series (if there is one)
                    $series = array();
                    try {
                        $repeatUntil = Alaress_Date_Format::newLocalShort(
                            $this->getRequest()->post['repeat_until']
                        );
                    } catch (\Exception $e) {
                        $repeatUntil = null;
                    }
                    if ($repeatUntil instanceof Alaress_Date_Format) {
                        $series = $this->getRecurrencePeriod(
                            $startDate,
                            $this->request->post['repeat'],
                            $repeatUntil->getDateTimeObject(),
                            $this->request->post['timetable']['campus']
                        );
                    }
                    $difference = $startDate->diff($endDate);
                    foreach ($series as $occurenceStart) {
                        $occurenceEnd = clone $occurenceStart;
                        $occurenceEnd->add($difference);
                        // Check all of $occurenceStart and $occurenceEnd
                        $dateParts[] = array($resource, $occurenceStart, $occurenceEnd);
                    }

                $relatedItems = $this->getRelatedItemsFromForm();
                foreach ($resource->getRelatedResourceList() as $relatedResource) {
                    if (array_key_exists($relatedResource->getId(), $relatedItems)) {
                        $dateParts[] = array($relatedResource, $startDate, $endDate);

                            foreach ($series as $occurenceStart) {
                                $occurenceEnd = clone $occurenceStart;
                                $occurenceEnd->add($difference);

                                // Check all of $occurenceStart and $occurenceEnd
                                $dateParts[] = array($resource, $occurenceStart, $occurenceEnd);
                            }
                        }
                    }

                foreach ($dateParts as $check) {
                    list($resource, $thisStart, $thisEnd) = $check;
                    $errors = array_merge_recursive(
                        $errors,
                        $this->validateForConflicts($resource, $thisStart, $thisEnd)
                    );
                }
            } else {
                // Validating an existing booking?
                // Check each item in the series, adjusting its dates.
                // Work with a fresh copy of the booking, because it can get
                // messed with.
                $isTimeChanged = false;
                $series = $this->getUpdatedSeries(
                    $this->bookingMapper->findById($existingBooking->getId()),
                    $startDate,
                    $endDate,
                    $this->getRelatedItemsFromForm(),
                    $this->request->post['efficacy'],
                    $isTimeChanged
                );

                    $dateParts = array();
                    foreach ($series as $thisBooking) {
                        if (!($thisBooking->isDeleted())) {
                            $dateParts[] = array($thisBooking->getResource(), $startDate, $endDate, $thisBooking);
                        }
                    }

                    foreach ($dateParts as $check) {
                        list($resource, $thisStart, $thisEnd, $thisBooking) = $check;
                        $errors = array_merge_recursive(
                            $errors,
                            $this->validateForConflicts($resource, $thisStart, $thisEnd, $thisBooking)
                        );
                    }
                }
            }
        }

        // If updating a booking and its timespan hasn't changed:
        // unset all timespan related errors.
        // This allows someone to change its notes.
        if ($existingBooking instanceof Model) {
            $existingStart = $existingBooking->getEvent()->getStartDateTime()->getDateTimeObject();
            $existingEnd   =  $existingBooking->getEvent()->getEndDateTime()->getDateTimeObject();
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

        // Conflicting bookings could include the existing booking.
        // Remove it, because a booking can't conflict with itself!
        // Also remove any items in the same series, because bookings can't
        // conflict with bookings in the same series (they're all offset at the
        // same time)
        // There may be rare occurrences where they can (a booking that is so
        // long it overlaps the next item in series!), but I'm not going to
        // worry about those.
        if ($existingBooking instanceof Model) {
            $existingId = $existingBooking->getId();
            if ($existingId) {
                $existingSeries = $existingBooking->getEvent()->getSeriesId();
                $conflictingBookings = $conflictingBookings->filter(
                    function($item) use ($existingId, $existingSeries) {
                        /** @var Model $item */
                        if ($item->getId() == $existingId) {
                            return false;
                        }
                        if (!empty($existingSeries) && $item->getEvent()->getSeriesId() == $existingSeries) {
                            return false;
                        }
                        return true;
                    }
                );
            }
        }

        // Anything still in list of conflicts? We have a conflict
        if (count($conflictingBookings)) {
            $errors['start_datetime'][] = 'conflict';
        } elseif (strlen($roomCode = $resource->getRoomCode()) > 0) {
            // Check if there's a timetabled class here, too.
            $roomSource = new \Calendar_Api_EventType_Timetable_Room(
                $this->getUser(),
                array()
            );
            $roomSource->setRoomCodes($roomCode);
            $roomEvents = $roomSource->getEvents(
                $startDate->getTimestamp() + 1,
                $endDate->getTimestamp() - 1
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
        try {
            $dateObject = Alaress_Date_Format::newLocalShort($this->getRequest()->post[$paramName]['date'])
                ->set12Hour(
                    $this->getRequest()->post[$paramName]['hour'],
                    $this->getRequest()->post[$paramName]['meridien']
                )
                ->setMinute($this->getRequest()->post[$paramName]['minute'])
            ;
        } catch (\Exception $e) {
            throw new \UnexpectedValueException('Could not build date out of form inputs', 0, $e);
        }
        return $dateObject->getDateTimeObject();
    }

    /**
     * Gets selected related items out of the form, along with notes and name.
     *
     * @return array
     */
    protected function getRelatedItemsFromForm()
    {
        $relatedItems = array();
        foreach ($this->request->post['related_item'] ?: array() as $relatedItem) {
            if ($relatedItem['resource_id']) {
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
            ->setName($this->getRequest()->post['name'])
            ->setNotes($this->getRequest()->post['notes'])
        ;
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
            $this->getUser(),
            array()
        );
        $source
            ->setResourceId($item->getId())
            ->setStatus($this->getRequest()->query['status']);

        $calendar = new Renderer(
            $source,
            $this->getRequest()->query['start'],
            $this->getRequest()->query['end'],
            $this->getUser(),
            $this->getDi()->get('sitePage')
        );

        $output = $calendar->renderAll('javascript');

        // Get timetabled classes for this room, if there are any.
        $roomCode = $item->getRoomCode();
        if (strlen($roomCode)) {
            $roomSource = new \Calendar_Api_EventType_Timetable_Room(
                $this->getUser(),
                array()
            );
            $roomSource->setRoomCodes($item->getRoomCode());
            $roomEvents   = $roomSource->getEvents(
                $this->getRequest()->query['start'],
                $this->getRequest()->query['end']
            );

            foreach ($roomEvents as $event) {
                $renderer = new Javascript( $this->getDi()->get('sitePage'),$event,  $this->getUser(), new \Group, new \Calendar);
                $output .= $renderer->render();
            }
        }

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
        $listGenerator = new TimetableArrayExporter($this->externalTimetableHandler);
        return $listGenerator->export($this->getUser());
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
                return $this->validateForConflicts(
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
     * Gets a series for update, adjusting their start and end dates.
     *
     * @param Model    $item
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param array    $relatedItems
     * @param string   $efficacy
     * @param bool&    $isTimeChanged Were any bookings changed?
     *
     * @return Collection|Model[]
     */
    protected function getUpdatedSeries(
        Model $item,
        DateTime $startDate,
        DateTime $endDate,
        array $relatedItems,
        $efficacy,
        &$isTimeChanged
    ) {
        // Get the date and time changes.
        $startDelta = Interval::createFromDateTimeDifference(
            $item->getEvent()->getStartDateTime()->getDateTimeObject(),
            $startDate
        );
        $endDelta = Interval::createFromDateTimeDifference(
            $item->getEvent()->getEndDateTime()->getDateTimeObject(),
            $endDate
        );
        $isTimeChanged = $startDelta->getSecondTotal() !== 0 || $endDelta->getSecondTotal() !== 0;

        /** @var Collection|Model[] $series */
        if ($efficacy === 'series') {
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

        $bookingList = new Collection();

        // Adjust the datetimes for the events in the series
        foreach ($series as $booking) {
            $event = $booking->getEvent();
            $newStart = $event->getStartDateTime()->getDateTimeObject()->add(
                $startDelta->getAsDateInterval()
            );
            $event->setStartDateTime(new Alaress_Date_Format($newStart));
            $newEnd = $event->getEndDateTime()->getDateTimeObject()->add(
                $endDelta->getAsDateInterval()
            );
            $event->setEndDateTime(new Alaress_Date_Format($newEnd));

            $bookingList->add($booking);

            // Set up bookings for related items
            $existingRelatedBookings = $this->bookingMapper->findRelatedBookings($booking->getId());
            foreach ($booking->getResource()->getRelatedResourceList() as $relatedResource) {
                $relatedBooking = null;
                foreach ($existingRelatedBookings as $existing) {
                    if ($existing->getResource()->getId() == $relatedResource->getId()) {
                        $relatedBooking = $existing;
                        break;
                    }
                }
                $inNew = array_key_exists($relatedResource->getId(), $relatedItems);

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
                            ->setResource($relatedResource)
                            ->setStatus(Status\Model::STATUS_PENDING);
                    }
                    $relatedBooking
                        ->setName($relatedItems[$relatedResource->getId()]['name'])
                        ->setNotes($relatedItems[$relatedResource->getId()]['notes'])
                    ;

                    // Open resource: automatically confirm
                    if ($relatedResource->getModerationType()->getId() == Item\ModerationType\Model::TYPE_OPEN) {
                        $relatedBooking->setStatus(Status\Model::STATUS_APPROVED);
                    }
                } elseif ($relatedBooking instanceof Model) {
                    // delete or don't add. Maybe mark as deleted?
                    // If it wasn't, then do nothing with it at all
                    $relatedBooking->setDeletedAt(new Alaress_Date_Format(new DateTime()));
                }
                if ($relatedBooking instanceof Model) {
                    $relatedBooking->setEvent($event);
                    $bookingList->add($relatedBooking);
                }
            }
        }

        return $bookingList;
    }
}
