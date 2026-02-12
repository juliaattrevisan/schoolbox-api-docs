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
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Collection;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\ResourceBooking\Booking\Model as BookingModel;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;
use Schoolbox\ResourceBooking\Booking\Status\Model as BookingStatusModel;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Item\Mapper as ResourceMapper;
use Schoolbox\ResourceBooking\Item\Model as BookingItemModel;
use Schoolbox\ResourceBooking\Item\ModerationType\Model as BookingModerationTypeModel;
use Schoolbox\User;
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
     * cache key for $this->conflictingBookings
     * Format:
     * $resource->getId() _ $startDate->getTimestamp() _ $endDate->getTimestamp() _ ($existingBooking ? $existingBooking->getId() : '')
     */
    private const CONFLICTING_BOOKINGS_CACHE_KEY = "%s_%s_%s_%s";

    /**
     * cache array if we are calling the validation multiple times, to avoid multiple calls to the database for the same data
     * we can always break the cache if needed
     * @var array<string, Collection<BookingModel>>
     */
    private array $conflictingBookings = [];

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
     * @param BookingModel|null $existingBooking If provided, this is the booking that is being updated.
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
        ?BookingModel $existingBooking = null
    ): array {
        // Check:
        // Beginning and end of booking are valid datetime
        // (These checks can't be overridden.)
        // TODO rewrite start/end checks to use Alaress_Date_Format directly
        $errors        = [];
        $startDate     = null;
        $endDate       = null;

        // Only users allowed to moderate this item may override validation
        $override = (
            !empty($postData['override_validation']) && $resource->canUserModerateThisItem($this->user)
        );

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
        // These validation errors cannot be overridden, because if the dates can't be parsed, we don't have a booking.
        if ($startDate === null || $endDate === null) {
            return $errors;
        }

        $errors = $this->validateSimpleRules($resource, $startDate, $endDate, $override);

        $series = new Series($this->getBookingMapper(), $this->getStatusMapper(), $this->getExternalTimetableHandler());

        if (!($existingBooking instanceof BookingModel)) {
            // new booking: Validate related and recurrent bookings
            $campus = null;
            if (($postData['campus'] ?? null) !== null) {
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
            $validationResults = [];
            foreach ($dateParts as $check) {
                /** @var Item\Model $thisResource */
                [$thisResource, $thisStart, $thisEnd] = $check;

                // Validate for matching the current term
                if (!$override && $thisResource->getCurrentTermRestriction()) {
                    $validationResults[] = $this->validateCurrentTermRestriction($thisEnd, $this->getCurrentTerm());
                }

                // Validate for clashes even if $override is true.
                // The clash validation results will allow us to find and reject the clashing bookings.
                // See $this->validateBookingIgnoringClashesIfClashesAllowed().
                $validationResults[] = $this->validateForClashes($thisResource, $thisStart, $thisEnd);
            }
            $errors = array_merge_recursive($errors, ...$validationResults);
        } else {
            // existing booking: Validate related and recurrent bookings
            $validationResults = [];
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
                if (!$override && $thisResource->getCurrentTermRestriction()) {
                    $validationResults[] = $this->validateCurrentTermRestriction($thisEnd, $this->getCurrentTerm());
                }

                // Validate for clashes even if $override is true.
                // The clash validation results will allow us to find and reject the clashing bookings.
                // See $this->validateBookingIgnoringClashesIfClashesAllowed().
                $validationResults[] = $this->validateForClashes($thisResource, $thisStart, $thisEnd, $thisBooking);
            }
            $errors = array_merge_recursive($errors, ...$validationResults);
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
     * @param DateTime   $startDate
     * @param DateTime   $endDate
     * @param bool       $override
     * @return array
     */
    public function validateSimpleRules(Item\Model $resource, DateTime $startDate, DateTime $endDate, bool $override): array
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
    public function validateChangeStatus(Model $booking, int $status): array
    {
        $bookingStartAt = $booking->getBookingStartAt() ?? $booking->getEvent()->getStartDateTime();
        $bookingEndAt = $booking->getBookingEndAt() ?? $booking->getEvent()->getEndDateTime();

        if ($bookingStartAt === null) {
            // This should never happen since we fall back to event start/end dates, but handle this theoretical case.
            return [self::START_PERIOD_ERRORS => [self::ERROR_INVALID => [self::ERROR_INVALID]]];
        }

        if ($bookingEndAt === null) {
            // This should never happen since we fall back to event start/end dates, but handle this theoretical case.
            return [self::END_PERIOD_ERRORS => [self::ERROR_INVALID => [self::ERROR_INVALID]]];
        }

        return match ($status) {
            Status\Model::STATUS_APPROVED => $this->validateForClashes(
                $booking->getResource(),
                $bookingStartAt->getDateTimeObject(),
                $bookingEndAt->getDateTimeObject(),
                $booking
            ),
            default => [],
        };
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

        /** @var Collection|Model[] $conflictingBookings */
        $conflictingBookings = $this->getConflictingBookings($resource, $startDate, $endDate, $existingBooking, true);

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
     * @param Model|null $existingBooking
     * @param bool       $breakCache // should we hit the db to find all booking, or use the cached ones?
     * @return Collection|Model[]
     */
    public function getConflictingBookings(Item\Model $resource, DateTime $startDate, DateTime $endDate, ?Model $existingBooking = null, bool $breakCache = false): Collection|array
    {
        // booking cache is a sum of the data of the function params
        $cacheKey = sprintf(self::CONFLICTING_BOOKINGS_CACHE_KEY, $resource->getId(), $startDate->getTimestamp(), $endDate->getTimestamp(), $existingBooking ? $existingBooking->getId() : '');

        if (!isset($this->conflictingBookings[$cacheKey]) || $breakCache) {
            // Only check approved bookings. Wow, the calendar population method
            // can do exactly that!
            // I do however want to allow a booking to start at the same time
            // that the previous booking ended.
            // (We may need to implement a down time between bookings.)
            $this->conflictingBookings[$cacheKey] = $this->getBookingMapper()->findForResource(
                [$resource->getId()],
                $startDate->getTimestamp() + 1,
                $endDate->getTimestamp() - 1,
                [Status\Model::STATUS_APPROVED]
            );
        }

        $conflictingBookings = $this->conflictingBookings[$cacheKey];

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

        return $conflictingBookings;
    }

    /**
     * Find and return all bookings that conflict with the given set of bookings.
     *
     * @param GenericCollection<BookingModel> $bookings
     * @return GenericCollection<BookingModel>
     */
    public function getConflictingBookingsForSetOfBookings(GenericCollection $bookings): GenericCollection
    {
        $allConflictingBookings = new GenericCollection(BookingModel::class);

        foreach ($bookings as $booking) {
            $startDatetime = $booking->getBookingStartAt() ?? $booking->getEvent()->getStartDateTime();
            $endDatetime = $booking->getBookingEndAt() ?? $booking->getEvent()->getEndDateTime();

            if ($startDatetime === null || $endDatetime === null) {
                // Handle this theoretical error case to prevent warnings below.
                continue;
            }

            $conflictingBookings = $this->getConflictingBookings(
                $booking->getResource(),
                $startDatetime->getDateTimeObject(),
                $endDatetime->getDateTimeObject(),
                $booking,
            );

            $allConflictingBookings = $allConflictingBookings->merge($conflictingBookings);
        }

        return $allConflictingBookings;
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

        return $timetableRoom->merge((new TimetableRoom(Calendar_Api_Config_Mapper::CONFIGURATION_NONE, $this->user, $this->user, $this->externalTimetableHandler, $roomCodes))
            ->getEvents($startDate->getTimestamp() + 1, $endDate->getTimestamp() - 1));
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
     * @param array<string, array<string, array<string>>> $errorsStruct Assumed to come from this class.
     * @return bool
     */
    public function validationErrorsAreAllClashes(array $errorsStruct): bool
    {
        if (count($errorsStruct) === 0) {
            throw new InvalidArgumentException('No errors, so this function should not have been called.');
        }

        return array_all(
            $this->getAllErrorStringsAsFlatArray($errorsStruct),
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
     * Validate the booking of a single resource $item, possibly as a recurrence.
     * If clash bookings are allowed for the resource, do not record clash validation errors.  Remember the dates on
     * which clashes are found however, and return those in a separate array.  This allows the caller to identify the
     * bookings that clash for special treatment (e.g. the caller may wish to set the status of those bookings to 'pending').
     *
     * @param Item\Model $item
     * @param array $bookingDetailsForItem - See docblock on for Validation->validateBookingFromPostData() for details.
     * @param null|Model $existingBooking
     * @return array
     * @throws MapperException
     * @throws Deleted
     */
    public function validateBookingIgnoringClashesIfClashesAllowed(
        Item\Model $item,
        array $bookingDetailsForItem,
        ?Model $existingBooking = null
    ): array {
        // Validate this item.
        $errorsStruct = $this->validateBookingFromPostData($item, $bookingDetailsForItem, $existingBooking);

        $clashBookingStartDatesAsKeys = [];
        if (count($errorsStruct) > 0) {
            // Remember the start dates of clash bookings.
            // Note clash errors always use the Validation::START_PERIOD_ERRORS key (see Validation->validateForClashes()).
            foreach ($errorsStruct[self::START_PERIOD_ERRORS] ?? [] as $startDate => $errors) {
                if (in_array($errors, [self::ERROR_CONFLICT, self::ERROR_TIMETABLED_CLASS], true)) {
                    $clashBookingStartDatesAsKeys[$startDate] = null;
                    break;
                }
            }

            // If clash bookings are allowed, or
            // a moderator user has ordered that all validation rules should be bypassed...
            if (
                $item->getAllowClashBookings() ||
                (!empty($bookingDetailsForItem['override_validation']) && $item->canUserModerateThisItem($this->user))
            ) {
                // Clear all the clash-related errors from the $errorsStruct array,
                // leaving only errors that cannot be overridden.
                // The clash-related errors will have been left in by $this->validateBookingFromPostData()
                // to allow us to find the start dates of clash bookings (as has been done above).
                $errorsStructFiltered = [];
                foreach ($errorsStruct as $key => $errorsByDate) {
                    foreach ($errorsByDate as $date => $errors) {
                        foreach ($errors as $error) {
                            if (!in_array($error, [self::ERROR_CONFLICT, self::ERROR_TIMETABLED_CLASS], true)) {
                                if (!array_key_exists($key, $errorsStructFiltered)) {
                                    $errorsStructFiltered[$key] = [];
                                }
                                if (!array_key_exists($date, $errorsStructFiltered[$key])) {
                                    $errorsStructFiltered[$key][$date] = [];
                                }
                                $errorsStructFiltered[$key][$date][] = $error;
                            }
                        }
                    }
                }
                $errorsStruct = $errorsStructFiltered;
                // Only non-overridable errors will remain after removing the clash errors above.
            }
        }

        return [$errorsStruct, $clashBookingStartDatesAsKeys];
    }

    /**
     * For each booking in $movedBookings, check whether the booking clashes with any existing approved bookings of the
     * same resource that end in the future.
     *
     * Notes:
     *  1. This function only checks against approved bookings that end in the future, because these are the only
     *     bookings that can clash with new bookings that start in the future.  New bookings that start in the past
     *     will always be rejected due to the self::ERROR_IN_PAST validation check.  It is the caller's responsibility
     *     to make that check.  A suggestion is to use $this->getNewStatusIdForBooking() to get the status of the
     *     moved bookings, which makes that check.
     *  2. If a booking does clash, the new booking can still be made if clash bookings are allowed on the resource.
     *
     * @param GenericCollection<int, BookingModel> $movedBookings
     *  - Bookings to check for clashes.  This array should contain bookings whose start/end times have been updated
     *    (so they differ from the values stored in the database).  This function will check for clashes with periods
     *    of approved bookings fetched from the database.  Note the database fetch query will exclude the periods of
     *    bookings in the $movedBookings array.
     * @param GenericCollection<int, BookingItemModel> $resourceById
     *  - Resources indexed by resourceId.  This array should contain all the resources that are booked in $movedBookings.
     * @return array<int, bool>
     *  - For each moved booking id, whether a clash was detected.
     */
    public function checkForClashesIfResourceBookingsMoved(
        GenericCollection $movedBookings,
        GenericCollection $resourceById,
    ): array {
        $isClashByBookingId = [];

        // See note 1 in the docblock for an explanation of why we only check against future approved bookings.
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
<<<<<<< HEAD
     * a clash with an already approved booking.
     *
     * Notes:
     *  1. This function only checks against approved bookings that end in the future, because these are the only
     *     bookings that can clash with new bookings that start in the future.  New bookings that start in the past
     *     will always be rejected due to the self::ERROR_IN_PAST validation check.  It is the caller's responsibility
     *     to make that check.  A suggestion is to use $this->getNewStatusIdForBooking() to get the status of the
     *     moved bookings, which makes that check.
     *  2. If a booking does clash, the new booking can still be made if clash bookings are allowed on the resource.
=======
     * a clash with an already approved booking.  (If so, the new booking can still be made if clash bookings are
     * allowed on the resource.)
>>>>>>> a51a229baf2 (Move function into Validation class [SA-21759])
     *
     * @param GenericCollection<int, BookingItemModel> $resourceById - Resources indexed by resourceId.
     * @param GenericCollection<int, Event> $eventById - Events indexed by eventId.
     * @return array<int, array<int, bool>>
     *  - For each event, for each resource id, whether a clash was detected.
     */
    public function checkForClashesIfResourceBookingsAddedToEvents(
        GenericCollection $resourceById,
        GenericCollection $eventById
    ): array {
        $isClashByResourceIdByEventId = [];

        // See note 1 in the docblock for an explanation of why we only check against future approved bookings.
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
                    if ($period['startDatetime']->gt($event->getEndDateTime())) {
                        // The $periods are sorted by start datetime ascending, so we can be sure that no other periods will clash with event.
                        break;
                    }

                    if (
                        $period['startDatetime']->lt($event->getEndDateTime()) &&
                        $period['endDatetime']->gt($event->getStartDateTime())
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
     *  - Whether the booking clashes with any other approved bookings (as supplied via $isClash)
     *
     * @param BookingModel $booking
     * @param bool $isClash
     * @param User $user
     * @param bool $overrideValidation
     * @return int
     */
    public function getNewStatusIdForBooking(
        BookingModel $booking,
        bool $isClash,
        User $user,
        bool $overrideValidation = false
    ): int {
        $resource = $booking->getResource();

        $bookingStartAt = $booking->getBookingStartAt() ?? $booking->getEvent()->getStartDateTime();
        $bookingEndAt = $booking->getBookingEndAt() ?? $booking->getEvent()->getEndDateTime();

        if ($bookingStartAt === null || $bookingEndAt === null) {
            // This should never happen since we fall back to event start/end dates, but handle this theoretical case.
            return BookingStatusModel::STATUS_REJECTED;
        }

        $errors = $this->validateSimpleRules(
            $resource,
            $bookingStartAt->getDateTimeObject(),
            $bookingEndAt->getDateTimeObject(),
            $overrideValidation,
        );

        if ($overrideValidation && count($errors) === 0) {
            // All the non-overridable validation rules have been checked, so we can approve.
            // Note if there were clashing bookings, they are assumed to have been dealt with by the calling function.
            // The clashing bookings should be dealt with by rejecting them and sending notifications.
            // The end result should be that no approved bookings for any resource overlap.
            return BookingStatusModel::STATUS_APPROVED;
        }

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
     * @param BookingModel $booking
     * @param User $user
     * @return int
     */
    private function getStatusIdForNonClashBooking(
        BookingModel $booking,
        User $user,
    ): int {
        $resource = $booking->getResource();
        $event = $booking->getEvent();

        // What about the resource? If it's an open resource, confirm the booking immediately.
        if ($resource->getModerationType()->getId() === BookingModerationTypeModel::TYPE_OPEN) {
            return BookingStatusModel::STATUS_APPROVED;
        }

        if (
            $resource->isUserModerator($user) // Note this function caches its results.
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
     * @return Collection
     */
    public function getClashEvents(
        Item\Model $resource,
        DateTime $startDate,
        DateTime $endDate,
        ?Model $existingBooking = null
    ): Collection {
        $conflictingBookings = new Collection();
        $conflictingBookings = $conflictingBookings->merge($this->getConflictingBookings($resource, $startDate, $endDate, $existingBooking));

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
