<?php

/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use Alaress\Web\Request\Values;
use Calendar\Event\Type\EventInterface;
use DateTime;
use Alaress_Date_Format;
use External_Timetable;
use InvalidArgumentException;
use Schoolbox\Calendar\Event;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Importer\Endpoint\ValidUsesSudsTimetable;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\ResourceBooking\Booking\Model as BookingModel;
use Schoolbox\ResourceBooking\Booking\Status\Model as BookingStatusModel;
use Schoolbox\ResourceBooking\Item\Model as BookingItemModel;
use Schoolbox\ResourceBooking\Item\ModerationType\Model as BookingModerationTypeModel;
use Schoolbox\User;
use Schoolbox\Collection;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Item\Mapper as ResourceMapper;
use Schoolbox\ResourceBooking\Booking\Model as Booking;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Calendar_Api_EventType_Timetable_Room as TimetableRoom;
use Calendar_Api_Config_Mapper;

/**
 * Class Validation
 *
 * @package Schoolbox\ResourceBooking\Booking
 */
class Validation
{
    const string START_PERIOD_ERRORS = 'start_period_errors'; // A key for errors pertaining to the start of the booking period.
    const string END_PERIOD_ERRORS = 'end_period_errors'; // A key for errors pertaining to the end of the booking period.
    const ERROR_INVALID = 'invalid';
    const ERROR_BEFORE_START = 'before_start';
    const ERROR_IN_PAST = 'in_past';
    const ERROR_TOO_EARLY = 'too_early';
    const ERROR_TOO_SHORT = 'too_short';
    const ERROR_TOO_LONG = 'too_long';
    const ERROR_CONFLICT = 'conflict';
    const ERROR_TIMETABLED_CLASS = 'timetabled_class';
    const ERROR_CURRENT_TERM = 'current_term';

    /**
     * @var User
     */
    private $user;

    /**
     * @var BookingMapper
     */
    private $bookingMapper;

    /**
     * @var StatusMapper
     */
    private $statusMapper;

    /**
     * @var ResourceMapper
     */
    private $resourceMapper;

    /**
     * @var TermMapper
     */
    private $termMapper;

    /**
     * @var External_Timetable
     */
    private $externalTimetableHandler;

    /**
     * @var CampusMapper
     */
    private $campusMapper;

    /**
     * @var \Schoolbox\IConfigLoader
     */
    private \Schoolbox\IConfigLoader $configLoader;

    /**
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;

    /**
     * @param User                             $user
     * @param BookingMapper                    $bookingMapper
     * @param StatusMapper                     $statusMapper
     * @param TermMapper                       $termMapper
     * @param External_Timetable               $externalTimetableHandler
     * @param CampusMapper                     $campusMapper
     * @param IConfigLoader                    $configLoader
     * @param \Schoolbox\External\Event\Mapper $eventMapper
     */
    public function __construct(
        User $user,
        BookingMapper $bookingMapper,
        StatusMapper $statusMapper,
        TermMapper $termMapper,
        External_Timetable $externalTimetableHandler,
        CampusMapper $campusMapper,
        \Schoolbox\IConfigLoader $configLoader,
        \Schoolbox\External\Event\Mapper $eventMapper
    ) {
        $this->user = $user;
        $this->setBookingMapper($bookingMapper);
        $this->setStatusMapper($statusMapper);
        $this->setTermMapper($termMapper);
        $this->setExternalTimetableHandler($externalTimetableHandler);
        $this->setCampusMapper($campusMapper);
        $this->setConfigLoader($configLoader);
        $this->setEventMapper($eventMapper);
    }

    /**
     * Attempts to validate the creation or update of a booking from post data.
     *
     * @param Item\Model   $resource
     * @param Values|array $postData Example:
     *        [
     *            // These values are those submitted by the form in Calendar/Event/templates/create.twig.
     *            'override_validation' => '1' or blank or unset
     *            'startDate' => datetime in local format (e.g. '02/12/2025 12:00pm')
     *            'endDate' => datetime in local format (e.g. '02/12/2025 12:00pm')
     *            'campus' => 0 or campusId
     *            'repeat_until' => date in local format (e.g. 02/12/2025)
     *            'related_item' => array of ['resource_id' => int, 'notes' => string]
     *            'repeat' => (E.g. 'daily', 'weekly', etc.  See the create.twig form)
     *        ]
     * @param Booking|null $existingBooking If provided, this is the booking that is being updated.
     *                                      Leave it out of conflict checks.
     * @return array<string, array<string, array<string>>> Example:
     *         [
     *             self::START_PERIOD_ERRORS => [
     *                 self::ERROR_INVALID => [
     *                     // Errors on invalid dates ('invalid' key is used because can't convert to desired format).
     *                     self::ERROR_INVALID
     *                 ],
     *                 // Errors on valid dates are keyed by the valid date in YYYY-MM-DD format.
     *                 // Since the smallest repeat period for series is 'daily', there will be at most one booking per day.
     *                 '2025-01-01' => [
     *                     self::ERROR_CONFLICT,
     *                     ...
     *                 ],
     *                 ...
     *             ],
     *             self::END_PERIOD_ERRORS => // Array with same structure as start_datetime.
     *         ]
     *
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function validateBookingFromPostData(
        Item\Model $resource,
        Values|array $postData,
        ?Booking $existingBooking = null
    ): array {
        // Check:
        // Beginning and end of booking are valid datetime
        // (These checks can't be overridden.)
        // TODO rewrite start/end checks to use Alaress_Date_Format directly
        $errors        = [];
        $startDate     = null;
        $endDate       = null;

        // Only users allowed to moderate this item may override validation
        $override = !empty($postData['override_validation'])
            && $resource->canUserModerateThisItem($this->user);

        try {
            $startDate = Alaress_Date_Format::newLocal($postData['startDate'])->getDateTimeObject();
        } catch (\Exception $e) {
            $errors[self::START_PERIOD_ERRORS][self::ERROR_INVALID][] = self::ERROR_INVALID;
        }

        try {
            $endDate = Alaress_Date_Format::newLocal($postData['endDate'])->getDateTimeObject();
        } catch (\Exception $e) {
            $errors[self::END_PERIOD_ERRORS][self::ERROR_INVALID][] = self::ERROR_INVALID;
        }

        // Return early if either date couldn't be parsed.
        if ($startDate === null || $endDate === null) {
            return $errors;
        }

        $errors = $this->validateSimpleRules($resource, $startDate, $endDate, $override);

        $series = new Series($this->getBookingMapper(), $this->getStatusMapper(), $this->getExternalTimetableHandler());

        if (!($existingBooking instanceof Booking)) {
            // new booking: Validate related and recurrent bookings
            $campus = null;
            if ($postData['campus'] > 0) {
                $campus = $this->getCampusMapper()->findById($postData['campus']);
            }
            /** @var Campus $campus */
            $dateParts = $series->getNewBookingSeries(
                $resource,
                $startDate,
                $endDate,
                $postData['repeat_until'] ?? null,
                $postData['related_item'] ?? null,
                $postData['repeat'] ?? null,
                $campus
            );
            foreach ($dateParts as $check) {
                /** @var Item\Model $thisResource */
                [$thisResource, $thisStart, $thisEnd] = $check;

                // Validate for matching the current term
                if ($thisResource->getCurrentTermRestriction()) {
                    $errors = array_merge_recursive(
                        $errors,
                        $this->validateCurrentTermRestriction($thisEnd, $this->getCurrentTerm())
                    );
                }

                $errors = array_merge_recursive(
                    $errors,
                    $this->validateForClashes($thisResource, $thisStart, $thisEnd)
                );
            }
        } else {
            // existing booking: Validate related and recurrent bookings
            foreach (
                $series->getExistingSeries(
                    $existingBooking,
                    $startDate,
                    $endDate,
                    $postData['related_item'] ?? null,
                    $postData['efficacy'] ?? null
                ) as $check
            ) {
                /** @var Item\Model $thisResource */
                [$thisResource, $thisStart, $thisEnd, $thisBooking] = $check;

                // Validate for matching the current term
                if ($thisResource->getCurrentTermRestriction()) {
                    $errors = array_merge_recursive(
                        $errors,
                        $this->validateCurrentTermRestriction($thisEnd, $this->getCurrentTerm())
                    );
                }

                $errors = array_merge_recursive(
                    $errors,
                    $this->validateForClashes($thisResource, $thisStart, $thisEnd, $thisBooking)
                );
            }
        }

        return $errors;
    }

    /**
     * Validation Methods
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return array
     */
    public function validateBeforeTime(DateTime $startDate, DateTime $endDate)
    {
        $errors = [];

        if ($startDate > $endDate) {
            $endDateKey = $endDate->format(Alaress_Date_Format::DATABASE_DATE_FORMAT);
            $errors[self::END_PERIOD_ERRORS][$endDateKey][] = self::ERROR_BEFORE_START;
        }

        return $errors;
    }

    /**
     * Validate start date not in the past
     *
     * @param DateTime $startDate
     * @return array
     */
    public function validatePastTime(DateTime $startDate)
    {
        $errors = [];
        $now    = new DateTime();
        $startDate->format('Y-m-d H:i');

        if ($startDate->format('Y-m-d H:i') < $now->format('Y-m-d H:i')) {
            $startDateKey = $startDate->format(Alaress_Date_Format::DATABASE_DATE_FORMAT);
            $errors[self::START_PERIOD_ERRORS][$startDateKey][] = self::ERROR_IN_PAST;
        }

        return $errors;
    }

    /**
     * Validate start date not too early
     *
     * @param Item\Model $resource
     * @param DateTime $startDate
     * @return array
     */
    public function validateLeadTime(Item\Model $resource, DateTime $startDate)
    {
        $errors = [];
        $earliest = new DateTime();
        $earliest->add($resource->getMinLeadTime()->getAsDateInterval());

        if ($startDate->format('Y-m-d H:i') < $earliest->format('Y-m-d H:i')) {
            $startDateKey = $startDate->format(Alaress_Date_Format::DATABASE_DATE_FORMAT);
            $errors[self::START_PERIOD_ERRORS][$startDateKey][] = self::ERROR_TOO_EARLY;
        }

        return $errors;
    }

    /**
     * Validate the minimum length of the booking
     *
     * @param Item\Model $resource
     * @param DateTime   $startDate
     * @param DateTime   $endDate
     * @return array
     */
    public function validateTooShort(Item\Model $resource, DateTime $startDate, DateTime $endDate)
    {
        $errors = [];
        // End date before earliest possible end
        $earliestEnd = clone $startDate;

        $earliestEnd->add($resource->getMinReservationTime()->getAsDateInterval());
        if ($endDate < $earliestEnd) {
            $endDateKey = $endDate->format(Alaress_Date_Format::DATABASE_DATE_FORMAT);
            $errors[self::END_PERIOD_ERRORS][$endDateKey][] = self::ERROR_TOO_SHORT;
        }

        return $errors;
    }

    /**
     * Validate the maximum length of the booking
     *
     * @param Item\Model $resource
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return array
     */
    public function validateTooLong(Item\Model $resource, DateTime $startDate, DateTime $endDate)
    {
        $errors = [];
        // End date after latest possible end (Only if there is an actual max time)
        if ($resource->getMaxReservationTime()->getSecondTotal() > 0) {
            $latestEnd = clone $startDate;
            $latestEnd->add($resource->getMaxReservationTime()->getAsDateInterval());
            if ($endDate > $latestEnd) {
                $endDateKey = $endDate->format(Alaress_Date_Format::DATABASE_DATE_FORMAT);
                $errors[self::END_PERIOD_ERRORS][$endDateKey][] = self::ERROR_TOO_LONG;
            }
        }

        return $errors;
    }

    /**
     * This function groups the simplest validation rule checks into one function to
     * avoid repetition when all those functions need to be called from multiple places.
     *
     * @param Item\Model $resource
     * @param            $startDate
     * @param            $endDate
     * @param            $override
     * @return array
     */
    public function validateSimpleRules(Item\Model $resource, $startDate, $endDate, $override): array
    {
        $errors = [];

        // Having a power to override doesn't make you a God. Start comes always before End.
        $errors = array_merge_recursive($errors, $this->validateBeforeTime($startDate, $endDate));

        // Bypass any other validation if overriding.
        if ($override) {
            return $errors;
        }

        $errors = array_merge_recursive($errors, $this->validatePastTime($startDate));
        $errors = array_merge_recursive($errors, $this->validateTooShort($resource, $startDate, $endDate));
        $errors = array_merge_recursive($errors, $this->validateTooLong($resource, $startDate, $endDate));
        $errors = array_merge_recursive($errors, $this->validateLeadTime($resource, $startDate));

        return $errors;
    }

    /**
     * Validates the status change for this booking
     *
     * @param Model $booking
     * @param int   $status
     *
     * @return array If not empty, this contains the errors for this change.
     */
    public function validateChangeStatus(Model $booking, $status)
    {
        switch ($status) {
            case Status\Model::STATUS_APPROVED:
                return $this->validateForClashes(
                    $booking->getResource(),
                    $booking->getEvent()->getStartDateTime()->getDateTimeObject(),
                    $booking->getEvent()->getEndDateTime()->getDateTimeObject(),
                    $booking
                );
            default:
                return [];
        }
    }

    /**
     * Validates whether a booking for this resource clashes with any bookings that already exist.
     * Note clashes include ERROR_CONFLICT and ERROR_TIMETABLED_CLASS errors.
     *
     * @param Item\Model $resource
     * @param DateTime   $startDate
     * @param DateTime   $endDate
     * @param Model|null $existingBooking
     * @return array
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function validateForClashes(
        Item\Model $resource,
        DateTime $startDate,
        DateTime $endDate,
        ?Model $existingBooking = null
    ): array {
        $errors = [];

        // Only check approved bookings. Wow, the calendar population method
        // can do exactly that!
        // I do however want to allow a booking to start at the same time
        // that the previous booking ended.
        // (We may need to implement a down time between bookings.)
        /** @var Collection|Model[] $conflictingBookings */
        $conflictingBookings = $this->getConflictingBookings($resource, $startDate, $endDate);

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
                $existingSeriesId    = $existingBooking->getEvent()->getSeriesId();
                $conflictingBookings = $conflictingBookings->filter(
                    /**
                     * @param Model $item
                     * @return bool
                     */
                    function ($item) use ($existingId, $existingSeriesId) {
                        /** @var Model $item */
                        if ($item->getId() == $existingId) {
                            return false;
                        }
                        if (!empty($existingSeriesId) && $item->getEvent()->getSeriesId() == $existingSeriesId) {
                            return false;
                        }

                        return true;
                    }
                );
            }
        }

        // Anything still in list of conflicts? We have a conflict
        if (count($conflictingBookings)) {
            $startDateKey = $startDate->format(Alaress_Date_Format::DATABASE_DATE_FORMAT);
            $errors[self::START_PERIOD_ERRORS][$startDateKey][] = self::ERROR_CONFLICT;
        } elseif (strlen($resource->getRoomCode() ?? '') > 0) {
            // Check if there's a timetabled class here, too.
            $roomEvents = $this->getTimetableRoomEvents(
                $resource->getRoomCode(),
                $startDate,
                $endDate
            );

            if (count($roomEvents)) {
                $startDateKey = $startDate->format(Alaress_Date_Format::DATABASE_DATE_FORMAT);
                $errors[self::START_PERIOD_ERRORS][$startDateKey][] = self::ERROR_TIMETABLED_CLASS;
            }
        }

        return $errors;
    }

    /**
     * Validates if booking needs to be within the current term
     *
     * @param DateTime $endDate
     * @param Term\Model $currentTerm
     * @return array
     */
    public function validateCurrentTermRestriction(DateTime $endDate, Term\Model $currentTerm)
    {
        $errors = [];

        $currentTermFinishDate = $currentTerm->getDisplayFinish()->getDateTimeObject();
        if ($endDate > $currentTermFinishDate) {
            $endDateKey = $endDate->format(Alaress_Date_Format::DATABASE_DATE_FORMAT);
            $errors[self::END_PERIOD_ERRORS][$endDateKey][] = self::ERROR_CURRENT_TERM;
        }

        return $errors;
    }

    /**
     * @return Term\Model
     */
    public function getCurrentTerm()
    {
        return $this->getTermMapper()->findForToday();
    }

    /**
     * Get all bookings for a resource in a window
     *
     * @param Item\Model $resource
     * @param DateTime   $startDate
     * @param DateTime   $endDate
     *
     * @return Collection|Model[]
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    private function getConflictingBookings(Item\Model $resource, DateTime $startDate, DateTime $endDate)
    {
        return $this->getBookingMapper()->findForResource(
            [$resource->getId()],
            $startDate->getTimestamp() + 1,
            $endDate->getTimestamp() - 1,
            [Status\Model::STATUS_APPROVED]
        );
    }

    /**
     * Get all events for a room in specified period
     *
     * @param string $roomCode
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return GenericCollection<EventInterface>
     */
    protected function getTimetableRoomEvents($roomCode, DateTime $startDate, DateTime $endDate)
    {
        // Using  [$roomCode => true] to make Calendar_Api_EventType_Timetable_Room::getEvents happy
        $roomCodes = [$roomCode => true];

        /* Combine schedule and cyclic timetable's data sets
 `       */
        $timetableRoom = (new \Calendar_Api_EventType_SudsTimetable_Room(Calendar_Api_Config_Mapper::CONFIGURATION_NONE, $this->user, $this->user, $this->eventMapper, $roomCodes))
            ->getEvents($startDate->getTimestamp() + 1, $endDate->getTimestamp() - 1);

        $timetableRoom->merge((new TimetableRoom(Calendar_Api_Config_Mapper::CONFIGURATION_NONE, $this->user, $this->user, $this->externalTimetableHandler, $roomCodes))
            ->getEvents($startDate->getTimestamp() + 1, $endDate->getTimestamp() - 1));

        return $timetableRoom;
    }

    /**
     * @return BookingMapper
     */
    public function getBookingMapper()
    {
        return $this->bookingMapper;
    }

    /**
     * @param  BookingMapper $bookingMapper
     *
     * @return $this
     */
    public function setBookingMapper($bookingMapper)
    {
        $this->bookingMapper = $bookingMapper;

        return $this;
    }

    /**
     * @return ResourceMapper
     */
    public function getResourceMapper()
    {
        return $this->resourceMapper;
    }

    /**
     * @param  ResourceMapper $resourceMapper
     *
     * @return $this
     */
    public function setResourceMapper($resourceMapper)
    {
        $this->resourceMapper = $resourceMapper;

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
     * @return External_Timetable
     */
    public function getExternalTimetableHandler()
    {
        return $this->externalTimetableHandler;
    }

    /**
     * @param External_Timetable $externalTimetableHandler
     */
    public function setExternalTimetableHandler($externalTimetableHandler)
    {
        $this->externalTimetableHandler = $externalTimetableHandler;
    }

    /**
     * @return CampusMapper
     */
    public function getCampusMapper()
    {
        return $this->campusMapper;
    }

    /**
     * @param CampusMapper $campusMapper
     */
    public function setCampusMapper($campusMapper)
    {
        $this->campusMapper = $campusMapper;
    }

    /**
     * @return \Schoolbox\IConfigLoader
     */
    public function getConfigLoader(): \Schoolbox\IConfigLoader
    {
        return $this->configLoader;
    }

    /**
     * @param IConfigLoader $configLoader
     * @return Validation
     */
    public function setConfigLoader(\Schoolbox\IConfigLoader $configLoader): self
    {
        $this->configLoader = $configLoader;
        return $this;
    }

    /**
     * @return Mapper
     */
    public function getEventMapper(): Mapper
    {
        return $this->eventMapper;
    }

    /**
     * @param Mapper $eventMapper
     * @return Validation
     */
    public function setEventMapper(Mapper $eventMapper): self
    {
        $this->eventMapper = $eventMapper;
        return $this;
    }

    /**
     * Return true if the given array is non-empty, and all values are either ERROR_CONFLICT or ERROR_TIMETABLED_CLASS.
     * @see validateForClashes()
     * @param array<string, array<string, array<string>>> $errorsByKey Assumed to come from this class.
     * @return bool
     */
    public function validationErrorsAreAllClashes(array $errorsByKey): bool
    {
        if (count($errorsByKey) === 0) {
            throw new InvalidArgumentException('No errors, so this function should not have been called.');
        }

        return array_all(
            $this->getAllErrorStringsAsFlatArray($errorsByKey),
            static fn ($error) => in_array($error, [self::ERROR_CONFLICT, self::ERROR_TIMETABLED_CLASS], true)
        );
    }

    /**
     * This function was added for backward-compatibility, so the total number of errors can be easily counted.
     * (As they could be before the date keys were added to the errors data structure.)
     * @see validateBookingFromPostData() docblock for an explanation of the $errorsStruct data structure.
     *
     * @param array<string, array<string, array<string>>> $errorsStruct
     * @return array<string>
     */
    public function getAllErrorStringsAsFlatArray(array $errorsStruct): array
    {
        $errorsFlattened = [];

        foreach ($errorsStruct as $keyIgnored => $errorsByDate) {
            foreach ($errorsByDate as $dateIgnored => $errors) {
                foreach ($errors as $error) {
                    $errorsFlattened[] = $error;
                }
            }
        }

        return $errorsFlattened;
    }

    /**
     * For each booking in $movedBookings, check whether the booking clashes with any existing approved bookings of the
     * same resource.  (If it does clash, the new booking can still be made if clash bookings are allowed on the resource.)
     * This function can be used to check for clashes efficiently when there is a large set of potential moves.
     * TODO: Move this function into ResourceBooking\Booking\Validation
     *
     * @param GenericCollection<int, BookingModel> $movedBookings
     *  - Bookings to check for clashes.  This array should contain bookings whose start/end times have been updated
     *    (so they differ from the values stored in the database).  This function will check for clashes with periods
     *    of approved bookings fetched from the database.  Note the database fetch query will exclude the periods of
     *    bookings in the $movedBookings array.
     * @param GenericCollection<int, BookingItemModel> $resourceById
     *  - Resources indexed by resourceId.  This array should contain all the resources that are booked in $movedBookings.
     * @return array
     */
    public function checkForClashesIfResourceBookingsMoved(
        GenericCollection $movedBookings,
        GenericCollection $resourceById,
    ): array {
        $isClashByBookingId = [];

        $periodsOfApprovedBookingsByResourceId = $this->bookingMapper->getPeriodsOfFutureApprovedBookingsByResourceId(
            $resourceById->keys(),
            sortByStartDatetimeAsc: true,
            // Exclude the moved bookings themselves when finding periods of approved bookings that potentially clash.
            bookingIdsToExclude: $movedBookings->map(fn ($movedBooking) => $movedBooking->getId(), $movedBookings)->getAll()
        );

        // For each booking, check for clashes with existing approved bookings of the same resource.
        /** @var BookingModel $movedBooking*/
        foreach ($movedBookings as $movedBooking) {
            $isClashByBookingId[$movedBooking->getId()] = false; // Assume no clash until a clash is found.
            $periodsOfApprovedBookings = $periodsOfApprovedBookingsByResourceId[$movedBooking->getResource()->getId()] ?? [];
            foreach ($periodsOfApprovedBookings as $period) {
                if ($period['startDatetime']->gt($movedBooking->getBookingEndAt())) {
                    // The $periods are sorted by start datetime ascending,
                    // so we can be sure that no other periods will clash with $movedBooking.
                    break;
                }

                if (
                    $period['startDatetime']->lt($movedBooking->getBookingEndAt()) &&
                    $period['endDatetime']->gt($movedBooking->getBookingStartAt())
                ) {
                    // If $movedBooking was persisted, it would clash with an approved booking for $period.
                    $isClashByBookingId[$movedBooking->getId()] = true;
                }
            }
        }

        return $isClashByBookingId;
    }

    /**
     * For each event in $eventById, check whether adding a booking of each resource in $resourceById would result in
     * a clash with an already approved booking.  (If so, the new booking can still be made if clash bookings are
     * allowed on the resource.)
     * TODO: Move this function into ResourceBooking\Booking\Validation
     *
     * @param GenericCollection<int, BookingItemModel> $resourceById - Resources indexed by resourceId.
     * @param GenericCollection<int, Event> $eventById - Events indexed by eventId.
     * @return array
     */
    public function checkForClashesIfResourceBookingsAddedToEvents(
        GenericCollection $resourceById,
        GenericCollection $eventById
    ): array {
        $isClashByResourceIdByEventId = [];

        $periodsOfApprovedBookingsByResourceId = $this->bookingMapper->getPeriodsOfFutureApprovedBookingsByResourceId(
            $resourceById->keys(),
            sortByStartDatetimeAsc: true
        );

        // For each event in the series, check for clash bookings if each resource is booked at a time matching the event time.
        /** @var Event $event */
        foreach ($eventById as $eventId => $event) {
            // For each resource, check for existing approved bookings of that resource that overlap the event period.
            foreach ($resourceById as $resourceId => $resource) {
                $periodsOfApprovedBookings = $periodsOfApprovedBookingsByResourceId[$resourceId] ?? [];
                foreach ($periodsOfApprovedBookings as $period) {
                    if ($period['startDatetime']->gt($event->getEndDatetime())) {
                        // The $periods are sorted by start datetime ascending, so we can be sure that no other periods will clash with event.
                        break;
                    }

                    if (
                        $period['startDatetime']->lt($event->getEndDatetime()) &&
                        $period['endDatetime']->gt($event->getStartDatetime())
                    ) {
                        if (!array_key_exists($eventId, $isClashByResourceIdByEventId)) {
                            $isClashByResourceIdByEventId[$eventId] = [];
                        }

                        // If $resource was booked for $event, that booking would clash with an approved booking for $period.
                        $isClashByResourceIdByEventId[$eventId][$resourceId] = true;
                    }
                }
            }
        }

        return $isClashByResourceIdByEventId;
    }

    /**
     * Return a suitable status for an updated resource booking, based on:
     *  - Any validation errors found
     *  - Whether the booking clashes with any other approved bookings (as supplied vai $isClash)
     *
     * @param BookingModel $booking
     * @param bool  $isClash
     * @param User $user
     * @return int
     */
    public function getNewStatusIdForBooking(BookingModel $booking, bool $isClash, User $user): int
    {
        $resource = $booking->getResource();

        $bookingStartAt = $booking->getBookingStartAt() ?? $booking->getEvent()->getStartDatetime();
        $bookingEndAt = $booking->getBookingEndAt() ?? $booking->getEvent()->getEndDatetime();

        if ($bookingStartAt === null || $bookingEndAt === null) {
            // This should never happen since we fall back to event start/end dates, but handle this theoretical case.
            return BookingStatusModel::STATUS_REJECTED;
        }

        $errors = $this->validateSimpleRules(
            $resource,
            $bookingStartAt->getDateTimeObject(),
            $bookingEndAt->getDateTimeObject(),
            false,
        );

        // This check is done separately since it is not included in $this->validateSimpleRules().
        if ($resource->getCurrentTermRestriction()) {
            $errors = array_merge_recursive(
                $errors,
                $this->validateCurrentTermRestriction($bookingEndAt->getDateTimeObject(), $this->getCurrentTerm())
            );
        }

        if (count($errors) > 0) {
            return BookingStatusModel::STATUS_REJECTED;
        }

        if ($isClash) {
            return (
                $resource->getAllowClashBookings()
                ? BookingStatusModel::STATUS_PENDING
                : BookingStatusModel::STATUS_REJECTED
            );
        }

        return $this->getStatusIdForNonClashBooking($booking, $user);
    }

    /**
     * Return the status to be set for a new booking that does not clash with any existing booking.
     * Note bookings that clash with existing bookings should be given status 'pending'.
     *
     * TODO: Modify this function so that no database operations are required.
     *       This should be done because this function is called from within a loop.
     *
     * @param BookingModel $booking
     * @param User $user
     * @return int
     */
    private function getStatusIdForNonClashBooking(BookingModel $booking, User $user): int
    {
        $resource = $booking->getResource();
        $event = $booking->getEvent();

        // What about the resource? If it's an open resource, confirm the
        // booking immediately.
        if ($resource->getModerationType()->getId() === BookingModerationTypeModel::TYPE_OPEN) {
            return BookingStatusModel::STATUS_APPROVED;
        }

        if (
            $resource->isUserModerator($user)
            && (int)$event->getAuthor()->getId() === (int)$user->getId()
        ) {
            /* If you're a Moderator and can moderate this request,
             * and you created the booking in the first place,
             * set it to Approved - otherwise you'll end up approving your own request anyway
             */
            return BookingStatusModel::STATUS_APPROVED;
        }

        return BookingStatusModel::STATUS_PENDING;
    }


    /**
     * fetch all clash events and timetable events that clash with the bookings
     *
     * based off @see \Schoolbox\ResourceBooking\Booking\Validation::validateForClashes
     * @param Item\Model $resource
     * @param DateTime   $startDate
     * @param DateTime   $endDate
     * @param Model|null $existingBooking
     * @return Collection|Model[]|EventInterface[]
     */
    public function getClashEvents(
        Item\Model $resource,
        DateTime $startDate,
        DateTime $endDate,
        ?Model $existingBooking = null
    ): Collection {
        // Only check approved bookings. Wow, the calendar population method
        // can do exactly that!
        // I do however want to allow a booking to start at the same time
        // that the previous booking ended.
        // (We may need to implement a down time between bookings.)
        /** @var Collection|Model[] $conflictingBookings */
        $conflictingBookings = $this->getConflictingBookings($resource, $startDate, $endDate);

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
                $existingSeriesId    = $existingBooking->getEvent()->getSeriesId();
                $conflictingBookings = $conflictingBookings->filter(
                /**
                 * @param Model $item
                 * @return bool
                 */
                    function ($item) use ($existingId, $existingSeriesId) {
                        /** @var Model $item */
                        if ($item->getId() == $existingId) {
                            return false;
                        }
                        if (!empty($existingSeriesId) && $item->getEvent()->getSeriesId() == $existingSeriesId) {
                            return false;
                        }

                        return true;
                    }
                );
            }
        }

        if (strlen($resource->getRoomCode() ?? '') > 0) {
            // Check if there's a timetabled class here, too.
            $conflictingBookings = $conflictingBookings->merge($this->getTimetableRoomEvents(
                $resource->getRoomCode(),
                $startDate,
                $endDate
            ));
        }

        return $conflictingBookings;
    }
}
