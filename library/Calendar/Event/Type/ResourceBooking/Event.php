<?php
/**
 * Calendar event for resource bookings.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Calendar\Event\Type\ResourceBooking;

use Calendar\Event\Type\EventInterface;
use Schoolbox\ResourceBooking\Booking\Model;
use Calendar\Event\Type\Day\Event as DayEvent;

class Event extends DayEvent implements EventInterface {

    /**
     * Renderer classes
     */
    protected $renderClasses = array(   'javascript'    => Renderer\Javascript::class,
                                        'html'          => Renderer\Html::class,
                                        'vcal'          => Renderer\VObject::class,
                                        'csv'           => Renderer\Csv::class,
    );

    /**
     * @var Model
     */
    protected $booking;

    /**
     * @param  Model $booking
     * @return $this
     */
    public function setBooking($booking) {
        $this->booking = $booking;
        return $this;
    }

    /**
     * @return Model
     */
    public function getBooking() {
        return $this->booking;
    }

    public function getTitle() {
        return $this->getBooking()->getName() ?: $this->getBooking()->getResource()->getName();
    }

    public function getLocation()
    {
        $location = $this->getBooking()->getResource()->getName();
        if ($this->getBooking()->getResource()->getRoomCode()) {
            $location .= ' ['.$this->getBooking()->getResource()->getRoomCode().']';
        }
        return $location;
    }

    public function getCategory() {
        return $this->getBooking()->getResource()->getCategory()->getName();
    }
}
