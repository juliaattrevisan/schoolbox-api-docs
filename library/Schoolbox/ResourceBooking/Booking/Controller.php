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
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Calendar;
use Schoolbox\Calendar\Event;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\TimetableArrayExporter;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\ResourceBooking\Item;
use Calendar\Event\Type\Timetable\Renderer\Javascript;
use Calendar\Event\Collection\Renderer;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;
use Schoolbox\ResourceBooking\Booking\Util\ResponseHelper as BookingResponseHelper;
use Schoolbox\Calendar\EventRecurrenceMapper;
use Calendar_Api_EventType_ResourceBooking_Booking_ForItem;

class Controller extends Base
{
    private const int HTTP_STATUS_CODE_OK = 200;
    private const int HTTP_STATUS_CODE_BAD_REQUEST = 400;

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
     * @var Validation
     */
    protected Validation $validation;

    /**
     * @param BookingResponseHelper $bookingResponseHelper
     */
    public function __construct(protected BookingResponseHelper $bookingResponseHelper)
    {
    }

    /**
     * Generates the 'Book Item' page.
     * The 'Book Item' page contains a form for creating a booking or series of bookings for a particular resource.
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
                'showQuantity'      => $this->shouldShowQuantityInput($booking),
                'quantityMax'       => $this->getMaxQuantity($booking),
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
                                        Recurrence::RECURRENCE_TIMETABLE => 'Timetable Based',
                                        ],
                'efficacySelf'      => EventRecurrenceMapper::EFFICACY_SELF,
                'efficacySeries'    => EventRecurrenceMapper::EFFICACY_SERIES,
            ],
        );
    }

    /**
     * Creates a booking for a particular resource.
     * This is called when the form on the 'Book Item' page is submitted.
     *
     * @param Item\Model $item The resource to create a booking for.
     * @throws MapperException
     */
    public function createBooking(Item\Model $item): void
    {
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
        [$errors, $clashBookingStartDatesAsKeys] = (
            $validation->validateBookingIgnoringClashesIfClashesAllowed($item, $this->requestPost->getArrayCopy())
        );

        $bookingList = null;
        $code = self::HTTP_STATUS_CODE_OK;

        // Only users allowed to moderate this item may override validation.
        // Determine this in the same way as is done in Validation->validateBookingFromPostData().
        $overrideValidation = (
            $this->requestPost->get('override_validation') &&
            $item->canUserModerateThisItem($this->user)
        );

        $nBookingsCreatedByStatusId = [];

        if (empty($errors)) {
            // Get the event that all bookings are based off of.
            $event = $this->getBaseEvent();
            $event->setStartDateTime(Alaress_Date_Format::newLocal($this->requestPost['startDate']));
            $event->setEndDateTime(Alaress_Date_Format::newLocal($this->requestPost['endDate']));
            $event->setTitle("Booking: " . $item->getName());
            $event->setDetail($this->requestPost['notes']);

            // Build the booking
            $booking = $this->getBookingFromEvent( // Booking status is set here.
                $item,
                $event,
                $clashBookingStartDatesAsKeys,
                $validation,
                $overrideValidation,
                $this->getQuantityFromForm(),
            );

            $bookingList = new GenericCollection(Model::class);
            $bookingList->add($booking);

            // Expand into bookings for recurrence
            if (!empty($this->requestPost['repeat'])) {
                $bookingList = $this->saveBookingsForRecurrences(
                    $bookingList,
                    $this->requestPost['repeat'],
                    Alaress_Date_Format::newLocalShort($this->requestPost['repeat_until'])->getDateTimeObject(),
                    $clashBookingStartDatesAsKeys,
                    $this->requestPost->getInt('campus'),
                    $validation,
                    $overrideValidation,
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
                $bookingList = $this->getBookingsForRelatedItems( // Booking status for related bookings is set here.
                    $bookingList,
                    $relatedItems,
                    $clashBookingStartDatesAsKeys,
                    $validation,
                    $overrideValidation,
                );
            }

            if ($overrideValidation) {
                $bookingsToReject = $validation->getConflictingBookingsForSetOfBookings($bookingList);
                foreach ($bookingsToReject as $bookingToReject) {
                    $bookingToReject->setStatus($this->getStatusMapper()->createModel(Status\Model::STATUS_REJECTED));
                }
                // Add them to the $bookingList so all modified bookings can be saved in one step below.
                $bookingList = $bookingList->merge($bookingsToReject);
            }

            $this->bookingMapper->save($bookingList);

            foreach ($bookingList as $booking) {
                if (!array_key_exists($booking->getStatus()->getId(), $nBookingsCreatedByStatusId)) {
                    $nBookingsCreatedByStatusId[$booking->getStatus()->getId()] = 0;
                }
                ++$nBookingsCreatedByStatusId[$booking->getStatus()->getId()];
            }
        } else {
            $code = self::HTTP_STATUS_CODE_BAD_REQUEST;
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
                    'message' => $this->bookingResponseHelper->getCreateResponseMessage($nBookingsCreatedByStatusId, 0),
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
     * Updates a booking for a particular resource.
     * This is called when the form on the 'Update Booking' page is submitted.
     *
     * @param Model $item
     * @throws MapperException
     */
    public function update(Model $item)
    {
        // Bookings linked to events of variant self::VARIANT_EVENT should be managed via the 'Modify Event' page.
        // Also note this function modifies the parent event in ways that may be inappropriate for those events.
        if ($item->getEvent()->getVariant() === Event::VARIANT_EVENT) {
            throw new SchoolboxException("Bookings linked to events cannot be updated by this method.");
        }

        // Only users allowed to moderate this item may override validation.
        // Determine this in the same way as is done in Validation->validateBookingFromPostData().
        $overrideValidation = (
            $this->requestPost->get('override_validation') &&
            $item->getResource()->canUserModerateThisItem($this->user)
        );

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
        [$errors, $clashBookingStartDatesAsKeys] = $validation->validateBookingIgnoringClashesIfClashesAllowed(
            $item->getResource(),
            $this->requestPost->getArrayCopy(),
            $item,
        );

        $originalStart = clone $item->getEvent()->getStartDateTime()->getDateTimeObject();
        $originalEnd   = clone $item->getEvent()->getEndDateTime()->getDateTimeObject();
        $originalQuantity = $item->getQuantity();
        $code = self::HTTP_STATUS_CODE_OK;
        $nBookingsUpdatedByStatusId = [];

        if (empty($errors)) {
            $item
                ->setName($this->requestPost['name'])
                ->setNotes($this->requestPost['notes'])
                ->setQuantity($this->getQuantityFromForm($item));
            // Note related bookings are included in $bookingSeries.
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
            $bookingList = new GenericCollection(Model::class);
            foreach ($series as $booking) {
                $event = $booking->getEvent();
                // Role and campus currently need to be set to something.
                $event->setRoleCollection(new Collection());
                $event->setCampusCollection(new Collection());
                $event->setDetail($booking->getNotes());
                $this->eventMapper->save($event);

                $bookingStartDateTime = $booking->getEvent()->getStartDateTime();
                $thisBookingHasClashes = (
                    $bookingStartDateTime !== null &&
                    array_key_exists($bookingStartDateTime->getDatabaseDate(), $clashBookingStartDatesAsKeys)
                );
                $bookingStatusId = $validation->getNewStatusIdForUpdatedBooking(
                    booking: $booking,
                    isClash: $thisBookingHasClashes,
                    user: $this->getUser(),
                    originalQuantity: $originalQuantity,
                    periodChanged: $isTimeChanged,
                    overrideValidation: $overrideValidation,
                );
                $booking->setStatus($this->getStatusMapper()->createModel($bookingStatusId));

                $bookingList->add($booking);
            }

            if ($overrideValidation) {
                // Note this must be done even if the booking times are unchanged.
                // E.g. A user updates a pending booking that overlaps an approved booking, and chooses to override validation.

                $bookingsToReject = $validation->getConflictingBookingsForSetOfBookings($bookingList);
                foreach ($bookingsToReject as $bookingToReject) {
                    $bookingToReject->setStatus($this->getStatusMapper()->createModel(Status\Model::STATUS_REJECTED));
                }
                // Add them to the $bookingList so all modified bookings can be saved in one step below.
                $bookingList = $bookingList->merge($bookingsToReject);
            }

            $this->bookingMapper->save($bookingList);

            foreach ($bookingList as $booking) {
                if (!array_key_exists($booking->getStatus()->getId(), $nBookingsUpdatedByStatusId)) {
                    $nBookingsUpdatedByStatusId[$booking->getStatus()->getId()] = 0;
                }
                ++$nBookingsUpdatedByStatusId[$booking->getStatus()->getId()];
            }

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
            $code = self::HTTP_STATUS_CODE_BAD_REQUEST;
        }

        /* Append the booking's id to the url, for the calling module to
         * process in whatever way it needs. This allows a decoupling between
         * Resource Booking and any modules that use it.
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
                    'message' => $this->bookingResponseHelper->getUpdateResponseMessage($nBookingsUpdatedByStatusId),
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
     * @param GenericCollection<Model> $bookingList
     * @param array                    $relatedItems
     * @param $clashBookingStartDatesAsKeys array<string, null> See docblock for $this->saveBookingsForRecurrences().
     * @param Validation $validation
     * @param bool $overrideValidation
     *
     * @return GenericCollection
     * @throws MapperException
     */
    protected function getBookingsForRelatedItems(
        GenericCollection $bookingList,
        array $relatedItems,
        array $clashBookingStartDatesAsKeys,
        Validation $validation,
        bool $overrideValidation,
    ): GenericCollection {
        if (empty($relatedItems)) {
            return $bookingList;
        }
        $newList = new GenericCollection(Model::class);
        foreach ($bookingList as $booking) {
            foreach ($booking->getResource()->getRelatedResourceList() as $relatedResource) {
                if (array_key_exists($relatedResource->getId(), $relatedItems)) {
                    $relatedItemInputs = $relatedItems[$relatedResource->getId()];
                    $relatedBooking    = $this->getBookingFromEvent( // Booking status is set here.
                        $relatedResource,
                        $booking->getEvent(),
                        $clashBookingStartDatesAsKeys,
                        $validation,
                        $overrideValidation,
                        Model::DEFAULT_QUANTITY,
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
     * Saves each item in a list of bookings into a series of bookings.
     *
     * @param GenericCollection<Model> $bookingList
     * @param string $recurrenceRule
     * @param DateTime $repeatUntil
     * @param array  $clashBookingStartDatesAsKeys array<string, null>
     *               Array containing start dates of bookings that clash with existing bookings.
     *               This function needs to know which bookings clash so those bookings can be saved with 'pending' status.
     *               Note the most frequent recurrence rule is 'daily', so the recurrence will have at most one booking per day.
     *               The dates are stored as keys to enable efficient checks using array_key_exists().
     *               The format of the dates is expected to be YYYY-MM-DD.
     * @param ?int $campusId
     * @param Validation $validation
     * @param bool $overrideValidation
     *
     * @return GenericCollection<Model>
     * @throws MapperException
     * @todo make this a mapper method
     */
    protected function saveBookingsForRecurrences(
        GenericCollection $bookingList,
        string $recurrenceRule,
        DateTime $repeatUntil,
        array $clashBookingStartDatesAsKeys,
        ?int $campusId,
        Validation $validation,
        bool $overrideValidation,
    ): GenericCollection {
        $newList = new GenericCollection(Model::class);
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
                    $newBooking->setStatus(
                        $this->getStatusMapper()->createModel(
                            $event->getStartDateTime() === null
                            ? Status\Model::STATUS_PENDING // Event start datetimes should always be set.  But if not, just use STATUS_PENDING.
                            : $validation->getNewStatusIdForBooking(
                                booking: $newBooking,
                                isClash: array_key_exists($event->getStartDateTime()->getDatabaseDate(), $clashBookingStartDatesAsKeys),
                                user: $this->getUser(),
                                overrideValidation: $overrideValidation,
                            )
                        )
                    );
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

    private function getQuantityFromForm(?Model $existingBooking = null): int
    {
        $quantity = $this->requestPost->get('quantity');

        if ($quantity === null || $quantity === '') {
            return $existingBooking?->getQuantity() ?? Model::DEFAULT_QUANTITY;
        }

        return max(Model::DEFAULT_QUANTITY, (int)$quantity);
    }

    private function shouldShowQuantityInput(Model $booking): bool
    {
        return $booking->getResource()->getCapacity() > Model::DEFAULT_QUANTITY
            || $booking->getQuantity() > Model::DEFAULT_QUANTITY;
    }

    private function getMaxQuantity(Model $booking): int
    {
        return max($booking->getResource()->getCapacity(), $booking->getQuantity());
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
        // this was created first as a booking, set it as such.
        $event->setVariant(Calendar\Event::VARIANT_BOOKING);
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
     * @param Item\Model     $resource
     * @param Calendar\Event $event
     * @param array          $clashBookingStartDatesAsKeys See docblock for $this->saveBookingsForRecurrences().
     * @param Validation     $validation
     * @param bool           $overrideValidation
     *
     * @return Model
     * @throws MapperException
     */
    protected function getBookingFromEvent(
        Item\Model $resource,
        Calendar\Event $event,
        array $clashBookingStartDatesAsKeys,
        Validation $validation,
        bool $overrideValidation,
        int $quantity = Model::DEFAULT_QUANTITY,
    ): Model {
        $booking = new Model();
        $booking
            ->setEvent($event)
            ->setResource($resource)
            ->setName($this->requestPost['name'])
            ->setNotes($this->requestPost['notes'])
            ->setQuantity($quantity)
            ->setRelatedBookingList(new GenericCollection(Model::class))
            ->setBookingStartAt(null) // Null here means 'the booking start time is the event start time'.
            ->setBookingEndAt(null);   // Null here means 'the booking end time is the event end time'.

        $bookingStartDatetime = $event->getStartDateTime();
        $booking->setStatus(
            $this->getStatusMapper()->createModel(
                $bookingStartDatetime === null
                ? Status\Model::STATUS_PENDING
                : $validation->getNewStatusIdForBooking(
                    booking: $booking,
                    isClash: array_key_exists($bookingStartDatetime->getDatabaseDate(), $clashBookingStartDatesAsKeys),
                    user: $this->getUser(),
                    overrideValidation: $overrideValidation,
                )
            )
        );

        return $booking;
    }

    /**
     * Outputs the calendar for this item.
     *
     * @param Item\Model $item
     * @throws \DomainException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws MapperException
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
     * Generate the 'Update Booking' page.
     * This page contains a form for editing a particular resource booking.
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
                'showQuantity'      => $this->shouldShowQuantityInput($item),
                'quantityMax'       => $this->getMaxQuantity($item),
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
                'efficacySeries'    => EventRecurrenceMapper::EFFICACY_SERIES,
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
                [
                    'id' => $item->getResource()->getId(),
                    'format' => '',
                ]
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
            )->withStatus(empty($errors) ? self::HTTP_STATUS_CODE_OK : self::HTTP_STATUS_CODE_BAD_REQUEST);
        } else {
            // Is there a previous location to redirect to? if so use that.
            $this->response = $this->redirectAfterPost(
                $callbackUrl ?: $this->getSession()->getHistory()
            );
        }
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

    /**
     * Handle approval of a booking that clashes with an existing booking.
     * rejects the old booking (approved) and approve the new booking. (pending)
     * @param Model $oldBooking
     * @param Model $item // item as we do route perm checks on $item so we can leverage that for the new booking
     *                    (both old booking and item (new booking) should be both the same resource, so if you have access to one, you have access to the other)
     * @return void
     * @throws SchoolboxException
     * @api GET /resource-booking/booking/{old}/{new}/clashApprove
     * @since 26.0
     */
    public function clashApprove(Model $oldBooking, Model $item)
    {
        // ensure these are a valid clash situation
        //     * Determine if this event clashes with another event
        //     * if it's the same event, return false
        //     * if different resources, return false
        //     * if either is a rejected booking, return false
        //     * if both are resource bookings, only care if one is pending
        //     * if both are resource bookings, skip if both pending or same booking id
        if ($oldBooking->getId() === $item->getId()) {
            throw new SchoolboxException("Cannot approve clash: bookings have the same ID.");
        }
        if ($oldBooking->getResource()->getId() !== $item->getResource()->getId()) {
            throw new SchoolboxException("Cannot approve clash: bookings are for different resources.");
        }
        if ($oldBooking->getStatus()->getId() === Status\Model::STATUS_REJECTED) {
            throw new SchoolboxException("Cannot approve clash: the old booking is rejected.");
        }
        if ($item->getStatus()->getId() === Status\Model::STATUS_REJECTED) {
            throw new SchoolboxException("Cannot approve clash: the new booking is rejected.");
        }
        if (
            $oldBooking->getStatus()->getId() === Status\Model::STATUS_APPROVED
            && $item->getStatus()->getId() === Status\Model::STATUS_APPROVED
        ) {
            throw new SchoolboxException("Cannot approve clash: both bookings are already approved.");
        }
        // Ensure old booking is approved and new booking is pending
        if (
            $oldBooking->getStatus()->getId() !== Status\Model::STATUS_APPROVED
            || $item->getStatus()->getId() !== Status\Model::STATUS_PENDING
        ) {
            throw new SchoolboxException("Unable to approve clash booking, only allowed when old booking is approved and new booking is pending.");
        }

        // reject the old booking, so we can approve the new one.
        $errorsOld = $this->changeStatus($oldBooking, Status\Model::STATUS_REJECTED);

        // if errors then exit here
        if (!empty($errorsOld)) {
            $this->setUpdateStatusResponse($oldBooking, $this->getStatusChangeCallbackUrl(), $errorsOld);
            return;
        }

        // approve the new booking
        $errorsNew = $this->changeStatus($item, Status\Model::STATUS_APPROVED);
        $this->setUpdateStatusResponse($item, $this->getStatusChangeCallbackUrl(), array_merge($errorsOld, $errorsNew));
    }
}
