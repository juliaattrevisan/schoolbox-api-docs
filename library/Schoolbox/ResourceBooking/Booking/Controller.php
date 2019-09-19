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
use Schoolbox\External\TimetableArrayExporter;
use Schoolbox\GenericCollection;
use Schoolbox\ResourceBooking\Item;
use Calendar\Event\Type\Timetable\Renderer\Javascript;
use Calendar\Event\Collection\Renderer;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;
use Schoolbox\ResourceBooking\Booking\Status;
use Schoolbox\Calendar\EventRecurrenceMapper;
use Calendar_Api_EventType_ResourceBooking_Booking_ForItem;

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

    /** @var TermMapper */

    private $termMapper;

    /**
     * @var StatusMapper
     */
    protected $statusMapper;

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
            ->setName($this->getRequest()->query['name'])
            ->setRelatedBookingList(new GenericCollection(Model::class));

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

        $this->getResponse()->content->set(
            [
                'template' => '@resourceBooking/book.twig',
                'data'     => [
                    'item'              => $booking,
                    'select'            => 'radio',
                    'campusList'        => $this->getCampusList(),
                    'callbackUrl'       => $this->getRequest()->query['callbackUrl'],
                    'allowOverride'     => $booking->getResource()->canUserModerateThisItem($this->getUser()),
                    'currentTerm'       => $this->getTermMapper()->findForToday(),
                    'validationData'    => [
                                            'start' => Validation::START_DATETIME,
                                            'end' => Validation::END_DATETIME,
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
            ]
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
        $validation = new Validation(
            $this->getUser(),
            $this->getBookingMapper(),
            $this->getStatusMapper(),
            $this->getTermMapper(),
            $this->getExternalTimetableHandler(),
            $this->getCampusMapper()
        );
        $errors = $validation->validateBookingFromPostData($item, null, $this->getRequest()->post);
        if (empty($errors)) {
            // Get the event that all bookings are based off of.
            $event = $this->getBaseEvent();
            $event->setStartDateTime(Alaress_Date_Format::newLocal($this->getRequest()->post['startDate']));
            $event->setEndDateTime(Alaress_Date_Format::newLocal($this->getRequest()->post['endDate']));
            $event->setTitle("Booking: " . $item->getName());
            $event->setDetail($this->getRequest()->post['notes']);

            // Build the booking
            $booking = $this->getBookingFromEvent($item, $event);
            $booking
                ->setName($this->request->post['name'])
                ->setNotes($this->request->post['notes']);
            $bookingList = new Collection();
            $bookingList->add($booking);

            // Expand into bookings for recurrence
            if (!empty($this->request->post['repeat'])) {
                $bookingList = $this->saveBookingsForRecurrences(
                    $bookingList,
                    $this->getRequest()->post['repeat'],
                    Alaress_Date_Format::newLocalShort($this->getRequest()->post['repeat_until'])->getDateTimeObject(),
                    $this->getRequest()->post['campus']
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
                [
                    'format' => 'json',
                    'data'   => [
                        'item'        => $bookingList,
                        'errors'      => $errors,
                        'callbackUrl' => $callbackUrl,
                    ],
                ]
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
        $bookingList   = null;
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
            $this->getCampusMapper()
        );
        $errors = $validation->validateBookingFromPostData($item->getResource(), $item, $this->getRequest()->post);

        if (empty($errors)) {
            $item
                ->setName($this->request->post['name'])
                ->setNotes($this->request->post['notes']);
            $bookingSeries = new Series($this->getBookingMapper(), $this->getStatusMapper(), $this->getExternalTimetableHandler());
            $series = $bookingSeries->getUpdatedSeries(
                $item,
                Alaress_Date_Format::newLocal($this->getRequest()->post['startDate'])->getDateTimeObject(),
                Alaress_Date_Format::newLocal($this->getRequest()->post['endDate'])->getDateTimeObject(),
                $this->getRelatedItemsFromForm(),
                $this->request->post['efficacy']
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

                //If item is moderated, already approved and time changed, set item to need approval again
                $moderationId = $booking->getResource()->getModerationType()->getId();
                $statusId     = $booking->getStatus()->getId();

                if (($moderationId == Item\ModerationType\Model::TYPE_MODERATED || $moderationId == Item\ModerationType\Model::TYPE_MANAGED) &&
                    $statusId == Status\Model::STATUS_APPROVED &&
                    $isTimeChanged) {
                    $booking->setStatus($this->getStatusMapper()->createModel(Status\Model::STATUS_PENDING));
                }

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
                [
                    'format' => 'json',
                    'data'   => [
                        'item'        => $item,
                        'errors'      => $errors,
                        'callbackUrl' => $callbackUrl,
                    ],
                ]
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
     * @todo make this a mapper method
     *
     * @param Collection|Model[] $bookingList
     * @param string             $recurrenceRule
     * @param DateTime           $repeatUntil
     * @param int                $campusId
     *
     * @return Collection
     */
    protected function saveBookingsForRecurrences(
        Collection $bookingList,
        $recurrenceRule,
        DateTime $repeatUntil,
        $campusId = null
    ) {
        $newList = new Collection();
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
                            []
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
     * Gets selected related items out of the form, along with notes and name.
     *
     * @return array
     */
    protected function getRelatedItemsFromForm()
    {
        $relatedItems = [];
        foreach ($this->request->post['related_item'] ?: [] as $relatedItem) {
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
            ->setStatus($this->getStatusMapper()->createModel(Status\Model::STATUS_PENDING))
            ->setName($this->getRequest()->post['name'])
            ->setNotes($this->getRequest()->post['notes'])
            ->setRelatedBookingList(new GenericCollection(Model::class));
        // What about the resource? If it's an open resource, confirm the
        // booking immediately.
        if ($resource->getModerationType()->getId() == Item\ModerationType\Model::TYPE_OPEN) {
            $booking->setStatus($this->getStatusMapper()->createModel(Status\Model::STATUS_APPROVED));
        }

        return $booking;
    }


    /**
     * Outputs the calendar for this item.
     *
     * @param Item\Model $item
     * @throws \DomainException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Exception
     */
    public function bookingCalendar(Item\Model $item)
    {
        // Output a calendar as JSON
        // For testing, output the calendar for current user first.
        // the find method should receive:
        // Datetime objects?
        /** @var Model[] $bookings */
        $source = new Calendar_Api_EventType_ResourceBooking_Booking_ForItem(
            Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
            $this->getUser(),
            $this->getUser(),
            [$item->getId()]
        );
        $source
            ->setStatus($this->getRequest()->query['status']);

        $calendar = new Renderer(
            $source,
            (int)$this->getRequest()->query['start'],
            (int)$this->getRequest()->query['end'],
            $this->getUser(),
            $this->getDi(),
            $this->getUser()
        );

        $output = $calendar->renderAll(EventBase::RENDER_FORMAT_JSON);

        // Get timetabled classes for this room, if there are any.
        $roomCode = $item->getRoomCode();

        if (!empty($roomCode)) {
            $roomSource = new \Calendar_Api_EventType_Timetable_Room(
                Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                $this->getUser(),
                $this->getUser(),
                $this->externalTimetableHandler,
                [$item->getRoomCode() => $item->getId()]
            );

            $roomEvents = $roomSource->getEvents(
                $this->getRequest()->query['start'],
                $this->getRequest()->query['end']
            );

            foreach ($roomEvents as $event) {
                $renderer = new Javascript(
                    $this->getDi(),
                    $event,
                    $this->getUser(),
                    $this->getUser()
                );
                $output[] = $renderer->render();
            }
        }

        $output = json_decode('[' . \implode(',', $output) . ']');

        $this->getResponse()->content->set(
            [
                'format' => 'json',
                'data'   => $output,
            ]
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
        $recurrence = [];
        if ($event->getSeriesId()) {
            $recurrence = $this->getEventRecurrenceMapper()->getSeriesRecurrence($event);
        }

        // Retrieve resource category details for display in breadcrumb
        $resId    = $item->getResource()->getId();
        $category = ($this->getResourceMapper()->findById($resId)->getCategory());
        $item->getResource()->setCategory($category);

        // Cancel callback URL: point to the last page if it wasn't set.
        $this->getResponse()->content->set(
            [
                'template' => '@resourceBooking/book.twig',
                'data'     => [
                    'item'              => $item,
                    'campusList'        => $this->getCampusList(),
                    'cancelCallbackUrl' => $this->session->getHistory(),
                    'allowOverride'     => $item->getResource()->canUserModerateThisItem($this->getUser()),
                    'currentTerm'       => $this->getTermMapper()->findForToday(),
                    'validationData'    => [
                        'start' => Validation::START_DATETIME,
                        'end' => Validation::END_DATETIME,
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
            ]
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
            $this->getCampusMapper()
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
        return $this->request->post['callbackUrl'] ?: $this->getSession()->getHistory();
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
        if ($callbackUrl === $this->router->generate('resourceBooking.edit', ['id' => $item->getId()])) {
            $callbackUrl = $this->router->generate(
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
        if ($this->getRequest()->query['efficacy'] === EventRecurrenceMapper::EFFICACY_SERIES
            || $this->getRequest()->post['efficacy'] === EventRecurrenceMapper::EFFICACY_SERIES
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
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->status->setCode(empty($errors) ? 200 : 400);
            $this->getResponse()->content->set(
                [
                    'format' => 'json',
                    'data'   => [
                        'item'        => $booking,
                        'callbackUrl' => $callbackUrl,
                        'errors'      => $errors,
                    ],
                ]
            );
        } else {
            // Is there a previous location to redirect to? if so use that.
            $this->getResponse()->redirect->afterPost(
                $callbackUrl ?: $this->getSession()->getHistory()
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

}
