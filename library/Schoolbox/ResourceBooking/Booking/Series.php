<?php

/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use DateTime;
use Alaress\Date\Interval;
use Alaress_Date_Format;
use Alaress\Date\Recurrence;
use Schoolbox\Collection;
use External_Timetable;
use Schoolbox\Campus;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Booking\Model as Booking;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\ResourceBooking\Booking\Status;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;
use Schoolbox\Calendar\EventRecurrenceMapper;

/**
 * Class Series
 *
 * @package Schoolbox\ResourceBooking\Booking
 */
class Series
{

    /**
     * @var BookingMapper
     */
    private $bookingMapper;


    /**
     * @var StatusMapper
     */
    protected $statusMapper;


    /**
     * @var External_Timetable
     */
    protected $externalTimetableHandler;


    /**
     * @param Mapper       $bookingMapper
     * @param StatusMapper $statusMapper
     */
    public function __construct(BookingMapper $bookingMapper, StatusMapper $statusMapper, External_Timetable $externalTimetableHandler)
    {
        $this->setBookingMapper($bookingMapper);
        $this->setStatusMapper($statusMapper);
        $this->setExternalTimetableHandler($externalTimetableHandler);
    }


    /**
     * Get all recursive booking dates
     *
     * @param Item\Model  $resource
     * @param DateTime    $startDate
     * @param DateTime    $endDate
     * @param String|null $repeatUntil
     * @param String|null $related
     * @param String|null $repeat
     * @param Campus|null $campus
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getNewBookingSeries(
        Item\Model $resource,
        DateTime $startDate,
        DateTime $endDate,
        $repeatUntil = null,
        $related = null,
        $repeat = null,
        Campus $campus = null
    ) {
        $dateParts = [];
        $series    = [];

        // Get all booking dates for the current resource
        if ($repeatUntil) {
            try {
                $repeatUntilDate = Alaress_Date_Format::newLocalShort(
                    $repeatUntil
                );
            } catch (\Exception $e) {
                $repeatUntilDate = null;
            }
            if ($repeatUntilDate instanceof Alaress_Date_Format) {
                $recurrence = new Recurrence($this->getExternalTimetableHandler());
                $series     = $recurrence->getRecurrencePeriod(
                    $startDate,
                    $repeat,
                    $repeatUntilDate->getDateTimeObject(),
                    $campus
                );
            }
            $difference = $startDate->diff($endDate);
            foreach ($series as $occurenceStart) {
                $occurenceEnd = clone $occurenceStart;
                $occurenceEnd->add($difference);
                // Check all of $occurenceStart and $occurenceEnd
                $dateParts[] = [$resource, $occurenceStart, $occurenceEnd];
            }
        } else {
            $dateParts[] = [$resource, $startDate, $endDate];
        }

        // Get all booking dates for all the related resources
        $relatedItems = [];
        foreach ($related ?? [] as $relatedItem) {
            if (isset($relatedItem['resource_id'])) {
                $relatedItems[$relatedItem['resource_id']] = $relatedItem;
            }
        }
        foreach ($resource->getRelatedResourceList() as $relatedResource) {
            // @phpstan-ignore-next-line: I don't know how this can be considered to always be false
            if (array_key_exists($relatedResource->getId(), $relatedItems)) {
                if (isset($difference)) {
                    foreach ($series as $occurenceStart) {
                        $occurenceEnd = clone $occurenceStart;
                        $occurenceEnd->add($difference);
                        $dateParts[] = [$relatedResource, $occurenceStart, $occurenceEnd];
                    }
                } else {
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
     * @param String $related
     * @param String $efficacy
     * @return array
     * @throws MapperException
     */
    public function getExistingSeries(
        Booking $existingBooking,
        DateTime $startDate,
        DateTime $endDate,
        $related = null,
        $efficacy = null
    ) {
        $relatedItems  = [];
        foreach ($related ?: [] as $relatedItem) {
            if ($relatedItem['resource_id'] ?? null) {
                $relatedItems[$relatedItem['resource_id']] = $relatedItem;
            }
        }
        $series = $this->getUpdatedSeries(
            $this->getBookingMapper()->findById($existingBooking->getId()),
            $startDate,
            $endDate,
            $relatedItems,
            $efficacy
        );

        $dateParts = [];
        foreach ($series as $thisBooking) {
            if (!$thisBooking->isDeleted()) {
                $dateParts[] = [
                    $thisBooking->getResource(),
                    $thisBooking->getEvent()->getStartDateTime()->getDateTimeObject(),
                    $thisBooking->getEvent()->getEndDateTime()->getDateTimeObject(),
                    $thisBooking,
                ];
            }
        }

        return $dateParts;
    }


    /**
     * Gets a series for update, adjusting their start and end dates.
     *
     * @param Model    $item
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param array    $relatedItems
     * @param string   $efficacy
     *
     * @return Collection|Model[]
     * @throws MapperException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getUpdatedSeries(
        Model $item,
        DateTime $startDate,
        DateTime $endDate,
        array $relatedItems,
        $efficacy
    ) {
        // Get the date and time changes.
        $startDelta = Interval::createFromDateTimeDifference(
            $item->getEvent()->getStartDateTime()->getDateTimeObject(),
            $startDate
        );
        $endDelta   = Interval::createFromDateTimeDifference(
            $item->getEvent()->getEndDateTime()->getDateTimeObject(),
            $endDate
        );

        $isTimeChanged = $startDelta->getSecondTotal() !== 0 || $endDelta->getSecondTotal() !== 0;

        if ($efficacy === EventRecurrenceMapper::EFFICACY_SERIES) {
            // Load whole series
            /** @var Collection|Model[] $series */
            $series = $this->getBookingMapper()->findSeriesBookings(
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
            // Update the purpose and notes for this booking
            $booking
                ->setName($item->getName())
                ->setNotes($item->getNotes());

            $event    = $booking->getEvent();
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
            $existingRelatedBookings = $this->getBookingMapper()->findRelatedBookings($booking->getId());
            foreach ($booking->getResource()->getRelatedResourceList() as $relatedResource) {
                /** @var Booking $relatedBooking */
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
                            $relatedBooking->setStatus(
                                $this->getStatusMapper()->createModel(Status\Model::STATUS_PENDING)
                            );
                        }
                    } else {
                        // create
                        $relatedBooking = $this->getBookingMapper()->createModel();
                        $relatedBooking
                            ->setResource($relatedResource)
                            ->setStatus($this->getStatusMapper()->createModel(Status\Model::STATUS_PENDING));
                    }
                    $relatedBooking
                        ->setName($item->getName())
                        ->setNotes($relatedItems[$relatedResource->getId()]['notes']);

                    // Open resource: automatically confirm
                    if ($relatedResource->getModerationType()->getId() == Item\ModerationType\Model::TYPE_OPEN) {
                        $relatedBooking->setStatus(
                            $this->getStatusMapper()->createModel(Status\Model::STATUS_APPROVED)
                        );
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
}
