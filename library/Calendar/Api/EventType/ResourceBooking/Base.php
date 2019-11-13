<?php
/**
 * Event source - base class for resource booking retrieval.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\EventTypeMapper;
use Calendar\Event\Collection;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\ResourceBooking\Booking\Status\Model as BookingStatus;
use Schoolbox\ResourceBooking\Booking\Util\EventConverter;
use Schoolbox\User;

abstract class Calendar_Api_EventType_ResourceBooking_Base extends Calendar_Api_EventType_Abstract
{

    /**
     * Statuses to filter out
     *
     * @var array
     */
    protected $status = [
        BookingStatus::STATUS_APPROVED,
        BookingStatus::STATUS_PENDING
    ];

    /**
     * @var BookingMapper
     */
    protected $bookingMapper;

    /**
     * @var EventConverter
     */
    protected $eventConverter;


    /**
     * Constructor
     *
     * @param int  $connectorId
     * @param User $user
     * @param User $viewer
     * @throws DomainException If resource booking module is disabled in config
     */
    public function __construct(int $connectorId, User $user, User $viewer)
    {
        parent::__construct($connectorId, $user, $viewer);
        // Ensure ResourceBooking module is enabled

        $config = ConfigLoader::getInstance();

        if (0 == $config->get('resource_booking_enabled')) {
            throw new DomainException('Resource Booking Module is disabled');
        }

        $this->setup();
    }

    /**
     * Sets up booking mapper and event converter
     */
    protected function setup()
    {
        $this->setBookingMapper(BookingMapper::getInstance())
            ->setEventConverter(new EventConverter());

        try {
            /** @var EventType $eventType */
            $eventType = EventTypeMapper::getInstance()->findOneByColumn('eventType', 'Booking');
            $this->getEventConverter()->setBookingEventType($eventType);

        } catch (MapperException $e) {
            // Not found: doesn't matter, don't use it
        }
    }

    /**
     * Get day events from this data source
     *
     * @param int $startTimestamp
     * @param int $endTimestamp
     * @return Collection
     */
    abstract public function getEvents($startTimestamp, $endTimestamp);

    /**
     * Find a particular event from this source.
     *
     * Not implemented - at least not yet - for resource booking items.
     *
     * @param $eventId
     * @return Collection
     */
    public function getEvent($eventId)
    {
        return new Collection;
    }

    /**
     * @param BookingMapper $bookingMapper
     * @return $this
     */
    public function setBookingMapper($bookingMapper)
    {
        $this->bookingMapper = $bookingMapper;
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
     * @param EventConverter $eventConverter
     * @return $this
     */
    public function setEventConverter($eventConverter)
    {
        $this->eventConverter = $eventConverter;
        return $this;
    }

    /**
     * @return EventConverter
     */
    public function getEventConverter()
    {
        return $this->eventConverter;
    }

    /**
     * @param array $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return array
     */
    public function getStatus()
    {
        return $this->status;
    }
}
