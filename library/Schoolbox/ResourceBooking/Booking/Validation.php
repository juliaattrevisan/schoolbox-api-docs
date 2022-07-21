<?php

/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use DateTime;
use Alaress_Date_Format;
use External_Timetable;
use Schoolbox\User;
use Schoolbox\Collection;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Item\Mapper as ResourceMapper;
use Schoolbox\ResourceBooking\Booking\Model as Booking;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\ResourceBooking\Booking\Status;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Aura\Web\Request;
use Calendar_Api_EventType_Timetable_Room as TimetableRoom;
use Calendar_Api_Config_Mapper;

/**
 * Class Validation
 *
 * @package Schoolbox\ResourceBooking\Booking
 */
class Validation
{
    const START_DATETIME = 'start_datetime';
    const END_DATETIME = 'end_datetime';
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
     * @param User               $user
     * @param Mapper             $bookingMapper
     * @param StatusMapper       $statusMapper
     * @param TermMapper         $termMapper
     * @param External_Timetable $externalTimetableHandler
     * @param CampusMapper       $campusMapper
     */
    public function __construct(
        User $user,
        BookingMapper $bookingMapper,
        StatusMapper $statusMapper,
        TermMapper $termMapper,
        External_Timetable $externalTimetableHandler,
        CampusMapper $campusMapper
    ) {
        $this->user = $user;
        $this->setBookingMapper($bookingMapper);
        $this->setStatusMapper($statusMapper);
        $this->setTermMapper($termMapper);
        $this->setExternalTimetableHandler($externalTimetableHandler);
        $this->setCampusMapper($campusMapper);
    }

    /**
     * Attempts to validate the creation or update of a booking from post data.
     *
     * @param Item\Model     $resource
     * @param Request\Values $postData
     * @param Booking        $existingBooking  If provided, this is the booking that is being updated.
     *                                         Leave it out of conflict checks.
     * @return array
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function validateBookingFromPostData(
        Item\Model $resource,
        $postData,
        Booking $existingBooking = null
    ) {
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
            $errors[self::START_DATETIME][] = self::ERROR_INVALID;
        }

        try {
            $endDate = Alaress_Date_Format::newLocal($postData['endDate'])->getDateTimeObject();
        } catch (\Exception $e) {
            $errors[self::END_DATETIME][] = self::ERROR_INVALID;
        }

        // Having a power to override doesn't make you a God. Start comes always before End.
        $errors = array_merge_recursive(
            $errors,
            $this->validateBeforeTime($startDate, $endDate)
        );

        // Bypass any other validation if overriding
        if ($override) {
            return $errors;
        }

        /**
         * we always want to check the start and end times
         */
        $errors = array_merge_recursive(
            $errors,
            $this->validatePastTime($startDate)
        );

        $errors = array_merge_recursive(
            $errors,
            $this->validateTooShort($resource, $startDate, $endDate)
        );

        $errors = array_merge_recursive(
            $errors,
            $this->validateTooLong($resource, $startDate, $endDate)
        );

        $errors = array_merge_recursive(
            $errors,
            $this->validateLeadTime($resource, $startDate)
        );

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
                    $this->validateForConflicts($thisResource, $thisStart, $thisEnd)
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
                    $this->validateForConflicts($thisResource, $thisStart, $thisEnd, $thisBooking)
                );
            }
        }

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
                return $this->validateForConflicts(
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
            $errors[self::END_DATETIME][] = self::ERROR_BEFORE_START;
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
            $errors[self::START_DATETIME][] = self::ERROR_IN_PAST;
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
            $errors[self::START_DATETIME][] = self::ERROR_TOO_EARLY;
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
            $errors[self::END_DATETIME][] = self::ERROR_TOO_SHORT;
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
                $errors[self::END_DATETIME][] = self::ERROR_TOO_LONG;
            }
        }

        return $errors;
    }

    /**
     * Validates whether a booking for this resource conflicts with any
     * bookings that already exist.
     *
     * @param Item\Model $resource
     * @param DateTime   $startDate
     * @param DateTime   $endDate
     * @param Model      $existingBooking
     * @return array
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function validateForConflicts(
        Item\Model $resource,
        DateTime $startDate,
        DateTime $endDate,
        Model $existingBooking = null
    ) {
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
            $errors[self::START_DATETIME][] = self::ERROR_CONFLICT;
        } elseif (strlen($roomCode = $resource->getRoomCode()) > 0) {
            // Check if there's a timetabled class here, too.
            $roomEvents = $this->getTimetableRoomEvents(
                $resource->getRoomCode(),
                $startDate,
                $endDate
            );

            if (count($roomEvents)) {
                $errors[self::START_DATETIME][] = self::ERROR_TIMETABLED_CLASS;
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
            $errors[self::END_DATETIME][] = self::ERROR_CURRENT_TERM;
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
     * @return \Calendar\Event\Collection
     */
    protected function getTimetableRoomEvents($roomCode, DateTime $startDate, DateTime $endDate)
    {
        // Using  [$roomCode => true] to make Calendar_Api_EventType_Timetable_Room::getEvents happy
        $timetableRoom = new TimetableRoom(Calendar_Api_Config_Mapper::CONFIGURATION_NONE, $this->user, $this->user, $this->externalTimetableHandler, [$roomCode => true]);

        return $timetableRoom->getEvents($startDate->getTimestamp() + 1, $endDate->getTimestamp() - 1);
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
}
