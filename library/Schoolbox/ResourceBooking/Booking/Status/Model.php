<?php
/**
 * Model for resource moderation types.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking\Status;

use JsonSerializable;

class Model implements JsonSerializable {

    /**
     * Pending booking
     */
    const STATUS_PENDING = 1;

    /**
     * Approved booking
     */
    const STATUS_APPROVED = 2;

    /**
     * Rejected booking
     */
    const STATUS_REJECTED = 3;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

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
     * @param  string $name
     * @return $this
     */
    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    public function isPending() {
        return $this->getId() == self::STATUS_PENDING;
    }

    public function isApproved() {
        return $this->getId() == self::STATUS_APPROVED;
    }

    public function isRejected() {
        return $this->getId() == self::STATUS_REJECTED;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    public function jsonSerialize() {
        return array(
            'id'   => $this->getId(),
            'name' => $this->getName(),
        );
    }

}
