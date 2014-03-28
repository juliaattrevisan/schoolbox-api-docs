<?php
/**
 * Join model for booking relations between bookings.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\ResourceBooking\Booking\RelatedBooking;
use Schoolbox\ResourceBooking\Booking\Model as Booking;

class Model {

    /**
     * @var int
     */
    protected $id;

    /**
     * @var Booking
     */
    protected $booking;

    /**
     * @var Booking
     */
    protected $relatedBooking;

    /**
     * @param  int   $id
     * @return $this
     */
    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param  Booking $relatedbooking
     * @return $this
     */
    public function setRelatedBooking($relatedbooking) {
        $this->relatedBooking = $relatedbooking;
        return $this;
    }

    /**
     * @return Booking
     */
    public function getRelatedBooking() {
        return $this->relatedBooking;
    }

    /**
     * @param  Booking $booking
     * @return $this
     */
    public function setBooking($booking) {
        $this->booking = $booking;
        return $this;
    }

    /**
     * @return Booking
     */
    public function getBooking() {
        return $this->booking;
    }

}
