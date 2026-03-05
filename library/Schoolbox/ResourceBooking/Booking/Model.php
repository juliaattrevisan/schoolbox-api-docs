<?php

/**
 * A representation of a booking of a resource.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ISoftDeletable;
use Schoolbox\Collection;
use Schoolbox\Calendar;
use Alaress_Date_Format;
use Schoolbox\User;
use JsonSerializable;

class Model implements ISoftDeletable, JsonSerializable
{
    use CreatedAtDate;
    use DeletedAtDate;

    /**
     * @var int
     */
    protected $id;

    /**
    * Name of the booking, which can be used to identify this booking
    * when using beavis suggests etc.
    *
    * @var string
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
    protected $modifiedAt;

    /**
     * @var null|Alaress_Date_Format
     */
    protected ?Alaress_Date_Format $bookingStartAt = null;

    /**
     * @var null|Alaress_Date_Format
     */
    protected ?Alaress_Date_Format $bookingEndAt = null;

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
        throw new \OutOfBoundsException('No related booking exists for resource ' . $resourceId);
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
     * @return ?Alaress_Date_Format
     */
    public function getBookingStartAt(): ?Alaress_Date_Format
    {
        return $this->bookingStartAt;
    }

    /**
     * @param  ?Alaress_Date_Format $bookingStartAt
     * @return self
     */
    public function setBookingStartAt(?Alaress_Date_Format $bookingStartAt): self
    {
        $this->bookingStartAt = $bookingStartAt;
        return $this;
    }

    /**
     * @return ?Alaress_Date_Format
     */
    public function getBookingEndAt(): ?Alaress_Date_Format
    {
        return $this->bookingEndAt;
    }

    /**
     * @param  ?Alaress_Date_Format $bookingEndAt
     * @return self
     */
    public function setBookingEndAt(?Alaress_Date_Format $bookingEndAt): self
    {
        $this->bookingEndAt = $bookingEndAt;
        return $this;
    }

    /**
     * See comments in docblock for canUserModifyBooking().
     * @param User $user
     * @return bool
     */
    public function canUserEditBooking(User $user): bool
    {
        return $this->canUserModifyBooking($user);
    }

    /**
     * See comments in docblock for canUserModifyBooking().
     * @param User $user
     * @return bool
     */
    public function canUserDeleteBooking(User $user): bool
    {
        return $this->canUserModifyBooking($user);
    }

    /**
     * This function was added so that methods in Calendar\Event\Controller that deal with bookings could check
     * booking permissions without having to infer a general permission (e.g. 'can a user edit a booking') from
     * permission to access a specific route (e.g. 'resourceBooking.edit').  Route permissions should only be used to
     * check access to that route.
     * The name 'modify' is intended to include 'update' and 'delete' operations.
     * A similar function canUserModerateThisItem() exists in ResourceBooking\Item\Model.
     *
     * @param User $user
     * @return bool - true means the user has permission to edit or delete this booking.
     */
    private function canUserModifyBooking(User $user): bool
    {
        if ($this->getResource()->canUserModerateThisItem($user)) {
            return true;
        }

        try {
            $bookingOwner = $this->getEvent()->getOwner();
        } catch (\TypeError) {
            throw new \UnexpectedValueException('Booking object does not have event owner set');
        }

        // If the user is the owner of the event, he/she can edit the booking.
        return ($bookingOwner->getId() === $user->getId());
    }

    /**
    * Specify data which should be serialized to JSON
    * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
    * @return array data which can be serialized by <b>json_encode</b>,
    * which is a value of any type other than a resource.
    */
    public function jsonSerialize(): array
    {
        // If booking start/end times are not set, use start/end times from the linked event.
        $bookingStartAt = $this->getBookingStartAt() ?? $this->getEvent()->getStartDateTime();
        $bookingEndAt = $this->getBookingEndAt() ?? $this->getEvent()->getEndDateTime();

        return [
            'id'       => $this->getId(),
            'name'     => $this->getName(),
            'notes'    => $this->getNotes(),
            'resource' => $this->getResource()->jsonSerialize(),
            'event'    => [
                'start' => $this->getEvent()->getStartDateTime()?->toIso8601String(),
                'end'   => $this->getEvent()->getEndDateTime()?->toIso8601String(),
            ],
            'start' => $bookingStartAt?->toIso8601String(),
            'end' => $bookingEndAt?->toIso8601String(),
            'status' => $this->getStatus()->jsonSerialize()
        ];
    }
}
