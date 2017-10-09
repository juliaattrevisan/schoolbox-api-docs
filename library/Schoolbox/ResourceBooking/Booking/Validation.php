<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use DateTime;
use Alaress_Date_Format;
use Alaress\Date\Interval;
use Schoolbox\User;
use Schoolbox\Collection;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Item\Mapper as ResourceMapper;
use Schoolbox\ResourceBooking\Booking\Model as Booking;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\ResourceBooking\Booking\Status;
use Schoolbox\Assessment\Term;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Aura\Web\Request;
use Calendar_Api_EventType_Timetable_Room as TimetableRoom;

class Validation {

    /**
     * Validate start before end
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     *
     * @return array
     */

    /**
     * @var BookingMapper
     */
    private $bookingMapper;

    /**
     * @var ResourceMapper
     */
    private $resourceMapper;

    /** @var TermMapper */

    private $termMapper;

    /** @var TimetableRoom */

    private $timetableRoom;

    public function __construct(
        User $user,
        BookingMapper $bookingMapper,
//        ResourceMapper $resourceMapper,
        TermMapper $termMapper
    )
    {

        $this->setUser($user);
        $this->setBookingMapper($bookingMapper);
//        $this->setResourceMapper($resourceMapper);
        $this->setTermMapper($termMapper);

    }

    /**
     * Attempts to validate the creation or update of a booking from post data.
     *
     * @param  Item\Model   $resource
     * @param  Booking    $existingBooking If provided, this is the booking that
     *                                     is being updated.
     *                                     Leave it out of conflict checks.
     * @param  Request\Values      $postData
     *
     * @return array
     */
    public function validateBookingFromPostData(
        Item\Model $resource,
        Booking $existingBooking = null,
        Request\Values $postData
    )
    {

        // Check:
        // Beginning and end of booking are valid datetime
        // (These checks can't be overridden.)
        // TODO rewrite start/end checks to use Alaress_Date_Format directly
        $errors        = [];
        $startDate     = null;
        $endDate       = null;
        $isNewBooking  = false;
        $isTimeChanged = false;

        // Only moderators of this booking may override validation
        $override = !empty($postData['override_validation'])
            && $resource->canUserModerateThisItem($this->getUser());

        try {
            $startDate = Alaress_Date_Format::newLocal($postData['startDate'])->getDateTimeObject();
        } catch (\Exception $e) {
            $errors['start_datetime'][] = 'invalid';
        }
        try {
            $endDate = Alaress_Date_Format::newLocal($postData['endDate'])->getDateTimeObject();
        } catch (\Exception $e) {
            $errors['end_datetime'][] = 'invalid';
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

        // New booking
        if (!($existingBooking instanceof Booking)) {
            $isNewBooking = true;
        } // Updated booking
        else {

            // Get the date and time changes from original booking
            $startDelta = Interval::createFromDateTimeDifference(
                clone $existingBooking->getEvent()->getStartDateTime()->getDateTimeObject(),
                $startDate
            );
            $endDelta   = Interval::createFromDateTimeDifference(
                clone $existingBooking->getEvent()->getEndDateTime()->getDateTimeObject(),
                $endDate
            );

            $isTimeChanged = $startDelta->getSecondTotal() !== 0 || $endDelta->getSecondTotal() !== 0;
        }

        // Validate any new booking or existing booking if dates have been modified
        if ($isNewBooking || $isTimeChanged) {

            $errors = array_merge_recursive(
                $errors,
                $this->validateLeadTime($resource, $startDate)
            );

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

            if ($isNewBooking) {
                // Validate related and recurrent bookings of a new booking
                $dateParts = $this->getNewBookingSeries($resource, $startDate, $endDate, $postData);
                foreach ($dateParts as $check) {
                    /** @var Resource $thisResource */
                    list($thisResource, $thisStart, $thisEnd) = $check;

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
                // Validating an existing booking?
                // Check each item in the series, adjusting its dates.
                // Work with a fresh copy of the booking, because it can get
                // messed with.

                foreach ($this->getExistingSeries($existingBooking, $startDate, $endDate) as $check) {
                    /** @var Resource $thisResource */
                    list($thisResource, $thisStart, $thisEnd, $thisBooking) = $check;

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

        }

        return $errors;

    }

    /**
     * Get all recursive booking dates
     *
     * @param Item\Model   $resource
     * @param DateTime   $startDate
     * @param DateTime   $endDate
     * @param Request\Values      $postData
     *
     * @return array
     */
    protected function getNewBookingSeries(
        Item\Model $resource,
        DateTime $startDate,
        DateTime $endDate,
        Request\Values $postData
    )
    {
        $dateParts = [];
        $series = [];

        // Get all booking dates for the current resource
        if ($postData['repeat_until']) {
            try {
                $repeatUntil = Alaress_Date_Format::newLocalShort(
                    $postData['repeat_until']
                );
            } catch (\Exception $e) {
                $repeatUntil = null;
            }
            if ($repeatUntil instanceof Alaress_Date_Format) {
                $series = $this->getRecurrencePeriod(
                    $startDate,
                    $postData['repeat'],
                    $repeatUntil->getDateTimeObject(),
                    $postData['timetable']['campus']
                );
            }
            $difference = $startDate->diff($endDate);
            foreach ($series as $occurenceStart) {
                $occurenceEnd = clone $occurenceStart;
                $occurenceEnd->add($difference);
                // Check all of $occurenceStart and $occurenceEnd
                $dateParts[] = [$resource, $occurenceStart, $occurenceEnd];
            }
        }
        else {
            $dateParts[] = [$resource, $startDate, $endDate];
        }

        // Get all booking dates for all the related resources
        $relatedItems = [];
        foreach ($postData['related_item'] ?: [] as $relatedItem) {
            if ($relatedItem['resource_id']) {
                $relatedItems[$relatedItem['resource_id']] = $relatedItem;
            }
        }
        foreach ($resource->getRelatedResourceList() as $relatedResource) {
            if (array_key_exists($relatedResource->getId(), $relatedItems)) {
                if (!empty($series)) {
                    foreach ($series as $occurenceStart) {
                        $occurenceEnd = clone $occurenceStart;
                        $occurenceEnd->add($difference);
                        $dateParts[] = [$relatedResource, $occurenceStart, $occurenceEnd];
                    }
                }
                else {
                    $dateParts[] = [$relatedResource, $startDate, $endDate];
                }
            }
        }

        return $dateParts;
    }

    /**
     * Get all related booking dates
     *
     * @param Booking  $existingBooking
     * @param DateTime $startDate
     * @param DateTime $endDate
     *
     * @return array
     * @throws MapperException
     */
    protected function getExistingSeries(Booking $existingBooking, DateTime $startDate, DateTime $endDate)
    {
        $isTimeChanged = false;
        $series        = $this->getUpdatedSeries(
            $this->bookingMapper->findById($existingBooking->getId()),
            $startDate,
            $endDate,
            $this->getRelatedItemsFromForm(),
            $this->request->post['efficacy'],
            $isTimeChanged
        );

        $dateParts = [];
        foreach ($series as $thisBooking) {
            if (!($thisBooking->isDeleted())) {
                $dateParts[] = [$thisBooking->getResource(),
                                $thisBooking->getEvent()->getStartDateTime()->getDateTimeObject(),
                                $thisBooking->getEvent()->getEndDateTime()->getDateTimeObject(),
                                $thisBooking];
            }
        }

        return $dateParts;
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
     * @param Item\Model
     * @param DateTime $startDate
     * @param DateTime $endDate
     *
     * @return array
     */
    private function getConflictingBookings(Item\Model $resource, DateTime $startDate, DateTime $endDate)
    {

        return $this->getBookingMapper()->findForResource(
            $resource->getId(),
            $startDate->getTimestamp() + 1,
            $endDate->getTimestamp() - 1,
            [Status\Model::STATUS_APPROVED]
        );

    }

    /**
     * Get all events for a room in specified period
     *
     * @param int $roomId
     * @param DateTime $startDate
     * @param DateTime $endDate
     *
     * @return \Calendar\Event\Collection
     */
    protected function getTimetableRoomEvents($roomId, DateTime $startDate, DateTime $endDate)
    {

        $timetableRoom = new TimetableRoom($this->getUser(), [], $roomId);

        return $timetableRoom->getEvents($startDate->getTimestamp() + 1, $endDate->getTimestamp() - 1);

    }

    /**
     * Validation Methods
     *
     */

    public function validateBeforeTime(DateTime $startDate, DateTime $endDate)
    {
        $errors = [];
        if ($startDate > $endDate) {
            $errors['end_datetime'][] = 'before_start';
        }

        return $errors;
    }

    /**
     * Validate start date not in the past
     *
     * @param DateTime   $startDate
     *
     * @return array
     */
    public function validatePastTime(DateTime $startDate)
    {
        $errors = [];
        $now    = new DateTime();
        if ($startDate < $now) {
            $errors['start_datetime'][] = 'in_past';
        }

        return $errors;
    }

    /**
     * Validate start date not too early
     *
     * @param Item\Model   $resource
     * @param DateTime   $startDate
     *
     * @return array
     */
    public function validateLeadTime(Item\Model $resource, DateTime $startDate)
    {
        $errors = [];
        $earliest = new DateTime();
        $earliest->add($resource->getMinLeadTime()->getAsDateInterval());
        if ($startDate < $earliest) {
            $errors['start_datetime'][] = 'too_early';
        }

        return $errors;
    }

    /**
     * Validate the minimum length of the booking
     *
     * @param Item\Model    $resource
     * @param DateTime      $startDate
     * @param DateTime      $endDate
     *
     * @return array
     */
    public function validateTooShort(Item\Model $resource, DateTime $startDate, DateTime $endDate)
    {
        $errors = [];
        // End date before earliest possible end
        $earliestEnd = clone $startDate;

        $earliestEnd->add($resource->getMinReservationTime()->getAsDateInterval());
        if ($endDate < $earliestEnd) {
            $errors['end_datetime'][] = 'too_short';
        }

        return $errors;
    }

    /**
     * Validate the maximum length of the booking
     *
     * @param Item\Model    $resource
     * @param DateTime   $startDate
     * @param DateTime   $endDate
     *
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
                $errors['end_datetime'][] = 'too_long';
            }
        }

        return $errors;
    }

    /**
     * Validates whether a booking for this resource conflicts with any
     * bookings that already exist.
     *
     * @param  Item\Model    $resource
     * @param  DateTime   $startDate
     * @param  DateTime   $endDate
     * @param  Model      $existingBooking
     *
     * @return array
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
                $existingSeries      = $existingBooking->getEvent()->getSeriesId();
                $conflictingBookings = $conflictingBookings->filter(
                    function ($item) use ($existingId, $existingSeries) {
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
        }
        elseif (strlen($roomCode = $resource->getRoomCode()) > 0) {

            // Check if there's a timetabled class here, too.
            $roomEvents = $this->getTimetableRoomEvents(
                $resource->getRoomCode(),
                $startDate,
                $endDate
            );

            if (count($roomEvents)) {
                $errors['start_datetime'][] = 'timetabled_class';
            }
        }

        return $errors;
    }

    /**
     * Validates if booking needs to be within the current term
     *
     * @param DateTime $endDate
     * @param Term\Model $currentTerm
     *
     * @return array
     */
    public function validateCurrentTermRestriction(DateTime $endDate, Term\Model $currentTerm)
    {
        $errors = [];

        $currentTermFinishDate = $currentTerm->getDisplayFinish()->getDateTimeObject();
        if ($endDate > $currentTermFinishDate) {
            $errors['end_datetime'][] = 'current_term';
        }

        return $errors;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param  User $user
     *
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
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