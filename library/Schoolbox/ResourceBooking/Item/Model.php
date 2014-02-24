<?php
/**
 * A representation of a bookable resource.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Item;

use Schoolbox\ResourceBooking\Category;
use Schoolbox\ISoftDeletable;
use Alaress_Date_Format;
use DateInterval;

class Model implements ISoftDeletable {

    /**
     * @var int
     */
    protected $id;

    /**
     * @var Category\Model
     */
    protected $category;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $description;

    /**
     * Minimum lead time, this should be a time object instead of a date format
     *
     * @var DateInterval
     */
    protected $minLeadTime;

    /**
     * @var DateInterval
     */
    protected $minReservationTime;

    /**
     * @var DateInterval
     */
    protected $maxReservationTime;

    /**
     * @var Alaress_Date_Format
     */
    protected $createdAt;

    /**
     * @var Alaress_Date_Format
     */
    protected $modifiedAt;

    /**
     * @var Alaress_Date_Format
     */
    protected $deletedAt;

    /**
     * @param  Category\Model $category
     * @return $this
     */
    public function setCategory($category) {
        $this->category = $category;
        return $this;
    }

    /**
     * @return Category\Model
     */
    public function getCategory() {
        return $this->category;
    }

    /**
     * @param  Alaress_Date_Format $createdAt
     *
     * @return $this
     */
    public function setCreatedAt($createdAt) {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt() {
        return $this->createdAt;
    }

    /**
     * @param  Alaress_Date_Format $deletedAt
     * @return $this
     */
    public function setDeletedAt($deletedAt) {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDeletedAt() {
        return $this->deletedAt;
    }

    /**
     * @param  string $description
     * @return $this
     */
    public function setDescription($description) {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }

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
     * @param  DateInterval $maxReservationTime
     * @return $this
     */
    public function setMaxReservationTime($maxReservationTime) {
        $this->maxReservationTime = $maxReservationTime;
        return $this;
    }

    /**
     * @return DateInterval
     */
    public function getMaxReservationTime() {
        return $this->maxReservationTime;
    }

    /**
     * @param  DateInterval $minLeadTime
     * @return $this
     */
    public function setMinLeadTime($minLeadTime) {
        $this->minLeadTime = $minLeadTime;
        return $this;
    }

    /**
     * @return DateInterval
     */
    public function getMinLeadTime() {
        return $this->minLeadTime;
    }

    /**
     * @param  DateInterval $minReservationTime
     * @return $this
     */
    public function setMinReservationTime($minReservationTime) {
        $this->minReservationTime = $minReservationTime;
        return $this;
    }

    /**
     * @return DateInterval
     */
    public function getMinReservationTime() {
        return $this->minReservationTime;
    }

    /**
     * @param  Alaress_Date_Format $modifiedAt
     * @return $this
     */
    public function setModifiedAt($modifiedAt) {
        $this->modifiedAt = $modifiedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getModifiedAt() {
        return $this->modifiedAt;
    }

    /**
     * @param  string $name
     *
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

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted() {
        return ($this->getDeletedAt() instanceof Alaress_Date_Format);
    }

}
