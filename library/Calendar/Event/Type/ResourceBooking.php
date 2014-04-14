<?php
/**
 * Calendar event type for resource bookings.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\ResourceBooking\Booking\Model;

class Calendar_Event_Type_ResourceBooking extends Calendar_Event_Type_Day {

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

}
