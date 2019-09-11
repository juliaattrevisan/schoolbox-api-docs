<?php
/**
 * A representation of a bookable resource.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use Schoolbox\ResourceBooking\Item;
use Schoolbox\ISoftDeletable;
use Schoolbox\Collection;
use Schoolbox\Calendar;
use Alaress_Date_Format;
use Schoolbox\User;
use JsonSerializable;

class Model implements ISoftDeletable, JsonSerializable
{

    /**
     * @var int
     */
    protected $id;

    /**
    * Name of the booking, which can be used to identify this booking
    * when using beavis suggests etc.
    *
    * @var
    */
    protected $name;

    /**
     * Notes for this resource booking, formerly stored in calendar event
     *
     * @var string
     */
    protected $notes;

    /**
     * @var Item\Model
     */
    protected $resource;

    /**
     * @var Calendar\Event
     */
    protected $event;

    /**
     * @var Status\Model
     */
    protected $status;

    /**
     * @var User
     */
    protected $reviewer;

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
     * @var Collection|self[]
     */
    protected $relatedBookingList;

    /**
     * @param  Alaress_Date_Format $modifiedAt
     * @return $this
     */
    public function setModifiedAt($modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }

    /**
     * @param  Alaress_Date_Format $createdAt
     *
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param  Alaress_Date_Format $deletedAt
     * @return $this
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * @param  int   $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  Item\Model $resource
     * @return $this
     */
    public function setResource($resource)
    {
        $this->resource = $resource;
        return $this;
    }

    /**
     * @return Item\Model
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @param  Calendar\Event $event
     * @return $this
     */
    public function setEvent($event)
    {
        $this->event = $event;
        return $this;
    }

    /**
     * @return Calendar\Event
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * @param  User  $reviewer
     * @return $this
     */
    public function setReviewer($reviewer)
    {
        $this->reviewer = $reviewer;
        return $this;
    }

    /**
     * @return User
     */
    public function getReviewer()
    {
        return $this->reviewer;
    }

    /**
     * @param  Status\Model $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return Status\Model
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param  Collection|self[] $relatedBookingList
     * @return $this
     */
    public function setRelatedBookingList($relatedBookingList)
    {
        $this->relatedBookingList = $relatedBookingList;
        return $this;
    }

    /**
     * @return Collection|self[]
     */
    public function getRelatedBookingList()
    {
        return $this->relatedBookingList;
    }

    /**
     * @param  self  $booking
     * @return $this
     */
    public function addRelatedBooking($booking)
    {
        $this->getRelatedBookingList()->add($booking);
        return $this;
    }

    /**
     * Does this resource booking have a related booking for the given resource?
     *
     * @param  int   $resourceId
     * @return Model
     * @throws \OutOfBoundsException if no related booking exists for $resourceId
     */
    public function getRelatedBookingForResource($resourceId)
    {
        foreach ($this->getRelatedBookingList() as $booking) {
            if ($booking->getResource()->getId() == $resourceId) {
                return $booking;
            }
        }
        throw new \OutOfBoundsException('No related booking exists for resource '.$resourceId);
    }

    /**
     * Does this resource booking have a related booking for the given resource?
     *
     * @param  int $resourceId
     * @return bool
     */
    public function hasRelatedBookingForResource($resourceId)
    {
        foreach ($this->getRelatedBookingList() as $booking) {
            if ($booking->getResource()->getId() == $resourceId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted()
    {
        return ($this->getDeletedAt() instanceof Alaress_Date_Format && !$this->getDeletedAt()->isNull());
    }

    /**
    * Set name
    *
    * @param mixed $name
    * @return $this
    */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
    * Get name
    *
    * @return mixed
    */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * @param  string $notes
     *
     * @return $this
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;
        return $this;
    }

    /**
    * Specify data which should be serialized to JSON
    * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
    * @return mixed data which can be serialized by <b>json_encode</b>,
    * which is a value of any type other than a resource.
    */
    public function jsonSerialize()
    {
        return array(
            'id'       => $this->getId(),
            'resource' => $this->getResource(),
            'event'    => array(
                'start' => $this->getEvent()->getStartDateTime()->toIso8601String(),
                'end'   => $this->getEvent()->getEndDateTime()->toIso8601String(),
            ),
        );
    }
}
