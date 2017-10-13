<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use DateTime;
use Alaress\Date\Interval;
use Alaress_Date_Format;
use Alaress\Date\Recurrence;
use Aura\Web\Request;
use InvalidArgumentException;
use Schoolbox\Collection;
use External_Timetable as ExternalTimetable;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Booking\Model as Booking;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\ResourceBooking\Booking\Status;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;

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
     * @var ExternalTimetable
     */
    protected $externalTimetable;

    /**
     * @param Mapper       $bookingMapper
     * @param StatusMapper $statusMapper
     */
    public function __construct(BookingMapper $bookingMapper, StatusMapper $statusMapper, ExternalTimetable $externalTimetable)
    {
        $this->setBookingMapper($bookingMapper);
        $this->setStatusMapper($statusMapper);
        $this->setExternalTimetable($externalTimetable);

    }

    /**
     * Get all recursive booking dates
     *
     * @param Item\Model     $resource
     * @param DateTime       $startDate
     * @param DateTime       $endDate
     * @param Request\Values $postData
     * @throws InvalidArgumentException
     * @return array
     */
    public function getNewBookingSeries(
        Item\Model $resource,
        DateTime $startDate,
        DateTime $endDate,
        Request\Values $postData
    ) {
        $dateParts = [];
        $series    = [];

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
                $recurrence = new Recurrence($this->getExternalTimetable());
                $series     = $recurrence->getRecurrencePeriod(
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
        } else {
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
     * @param iterable $postData
     * @return array
     * @throws MapperException
     */
    public function getExistingSeries(Booking $existingBooking, DateTime $startDate, DateTime $endDate, $postData)
    {
        $isTimeChanged = false;
        $relatedItems  = [];
        foreach ($postData['related_item'] ?: [] as $relatedItem) {
            if ($relatedItem['resource_id']) {
                $relatedItems[$relatedItem['resource_id']] = $relatedItem;
            }
        }
        $series = $this->getUpdatedSeries(
            $this->getBookingMapper()->findById($existingBooking->getId()),
            $startDate,
            $endDate,
            $relatedItems,
            $postData['efficacy'],
            $isTimeChanged
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
     * @param bool     &    $isTimeChanged Were any bookings changed?
     *
     * @return Collection|Model[]
     * @throws MapperException
     */
    public function getUpdatedSeries(
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
        $endDelta   = Interval::createFromDateTimeDifference(
            $item->getEvent()->getEndDateTime()->getDateTimeObject(),
            $endDate
        );

        $isTimeChanged = $startDelta->getSecondTotal() !== 0 || $endDelta->getSecondTotal() !== 0;

        /** @var Collection|Model[] $series */
        if ($efficacy === 'series') {
            // Load whole series
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
                        ->setName($relatedItems[$relatedResource->getId()]['name'])
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
     * @return ExternalTimetable
     */
    public function getExternalTimetable()
    {
        return $this->externalTimetable;
    }

    /**
     * @param ExternalTimetable $externalTimetable
     */
    public function setExternalTimetable($externalTimetable)
    {
        $this->externalTimetable = $externalTimetable;
    }
}
