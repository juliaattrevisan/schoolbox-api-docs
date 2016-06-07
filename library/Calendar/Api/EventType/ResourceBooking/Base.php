<?php
/**
 * Event source - base class for resource booking retrieval.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\ResourceBooking\Booking;
use Schoolbox\Mapper;
use Schoolbox\Calendar\EventTypeMapper;
use Calendar\Event\Collection;

abstract class Calendar_Api_EventType_ResourceBooking_Base extends Calendar_Api_EventType_Abstract {

    /**
     * Statuses to filter out
     *
     * @var array
     */
    protected $status = array(
        Booking\Status\Model::STATUS_APPROVED,
        Booking\Status\Model::STATUS_PENDING
    );

    /**
     * @var Booking\Mapper
     */
    protected $bookingMapper;

    /**
     * @var Booking\Util\EventConverter
     */
    protected $eventConverter;

    /**
     * Constructor
     *
     * @param $user
     * @param $config
     */
    public function __construct($user, $config) {

        //Ensure ResourceBooking module is enabled
        $configMapper = \Schoolbox\Config\ConfigMapper::getInstance();
        if(0 == $configMapper->get('resource_booking_enabled')){
            throw new DomainException('Resource Booking Module is disabled');
        }


        $this->setUser($user);
        $this->setup();
    }

    /**
     * Sets up booking mapper and event converter
     */
    protected function setup() {
        $this
            ->setBookingMapper(Booking\Mapper::getInstance())
            ->setEventConverter(new Booking\Util\EventConverter());
        try {
            $this->getEventConverter()->setBookingEventType(
                EventTypeMapper::getInstance()->findOneByColumn('eventType', 'Booking')
            );
        }
        catch (Mapper\Exception $e) {
            // Not found: doesn't matter, don't use it
        }
    }

    /**
     * Get day events from this data source
     *
     * @param int $startTimestamp
     * @param int $endTimestamp
     *
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
    public function getEvent($eventId) {}

    /**
     * @param  Booking\Mapper $bookingMapper
     * @return $this
     */
    public function setBookingMapper($bookingMapper) {
        $this->bookingMapper = $bookingMapper;
        return $this;
    }

    /**
     * @return Booking\Mapper
     */
    public function getBookingMapper() {
        return $this->bookingMapper;
    }

    /**
     * @param  Booking\Util\EventConverter $eventConverter
     * @return $this
     */
    public function setEventConverter($eventConverter) {
        $this->eventConverter = $eventConverter;
        return $this;
    }

    /**
     * @return Booking\Util\EventConverter
     */
    public function getEventConverter() {
        return $this->eventConverter;
    }

    /**
     * @param  array $status
     * @return $this
     */
    public function setStatus($status) {
        $this->status = $status;
        return $this;
    }

    /**
     * @return array
     */
    public function getStatus() {
        return $this->status;
    }

}
