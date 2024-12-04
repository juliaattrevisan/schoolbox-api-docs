<?php

/**
 * A representation of a bookable resource.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Item;

use Schoolbox\Collection;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\ResourceBooking\Category;
use Schoolbox\ISoftDeletable;
use Schoolbox\Resource\Storage;
use Alaress_Date_Format;
use Alaress\Date\Interval;
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
     * @var Category\Model
     */
    protected $category;

    /**
     * @var null|Storage\Model
     */
    protected ?Storage\Model $image;

    /**
     * @var ModerationType\Model
     */
    protected $moderationType;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $roomCode;

    /**
     * @var string
     */
    protected $description;

    /**
     * Minimum lead time, this should be a time object instead of a date format
     *
     * @var Interval
     */
    protected $minLeadTime;

    /**
     * @var Interval
     */
    protected $minReservationTime;

    /**
     * @var Interval
     */
    protected $maxReservationTime;

    /**
     * @var Boolean
     */
    protected $currentTermRestriction;

    /**
     * @var Alaress_Date_Format
     */
    protected $modifiedAt;

    /**
     * @var Collection|User[]
     */
    protected $moderatorList;

    /**
     * @var Collection|self[]
     */
    protected $relatedResourceList;

    /**
     * @var Collection|RolePermission\Model[]
     */
    protected $roleList;

    /**
     * @var bool
     */
    protected $bookingIsAuthorized = false;

    public function __construct()
    {
        $this->setImage(new Storage\Model());
    }

    /**
     * @param  Category\Model $category
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return Category\Model
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param  string $description
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
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
     * @param  Interval $maxReservationTime
     * @return $this
     */
    public function setMaxReservationTime($maxReservationTime)
    {
        $this->maxReservationTime = $maxReservationTime;
        return $this;
    }

    /**
     * @return Interval
     */
    public function getMaxReservationTime()
    {
        return $this->maxReservationTime;
    }

    /**
     * @param  Boolean $currentTermRestriction
     * @return $this
     */
    public function setCurrentTermRestriction($currentTermRestriction)
    {
        $this->currentTermRestriction = $currentTermRestriction;
        return $this;
    }

    /**
     * @return Boolean
     */
    public function getCurrentTermRestriction()
    {
        return $this->currentTermRestriction;
    }

    /**
     * @param  Interval $minLeadTime
     * @return $this
     */
    public function setMinLeadTime($minLeadTime)
    {
        $this->minLeadTime = $minLeadTime;
        return $this;
    }

    /**
     * @return Interval
     */
    public function getMinLeadTime()
    {
        return $this->minLeadTime;
    }

    /**
     * @param  Interval $minReservationTime
     * @return $this
     */
    public function setMinReservationTime($minReservationTime)
    {
        $this->minReservationTime = $minReservationTime;
        return $this;
    }

    /**
     * @return Interval
     */
    public function getMinReservationTime()
    {
        return $this->minReservationTime;
    }

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
     * @param  string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param  string|null $roomCode
     * @return $this
     */
    public function setRoomCode($roomCode)
    {
        $this->roomCode = $roomCode;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getRoomCode()
    {
        return $this->roomCode;
    }

    /**
     * @param  ModerationType\Model $moderationType
     * @return $this
     */
    public function setModerationType($moderationType)
    {
        $this->moderationType = $moderationType;
        return $this;
    }

    /**
     * @return ModerationType\Model
     */
    public function getModerationType()
    {
        return $this->moderationType;
    }

    /**
     * @param  null|Storage\Model $image
     * @return $this
     */
    public function setImage($image)
    {
        $this->image = $image;
        return $this;
    }

    /**
     * @return null|Storage\Model
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * @param  Collection|User[]|null $moderatorList
     * @return $this
     */
    public function setModeratorList($moderatorList)
    {
        $this->moderatorList = $moderatorList;
        return $this;
    }

    /**
     * @return Collection|User[]|null
     */
    public function getModeratorList()
    {
        return $this->moderatorList;
    }

    /**
     * @param  User $moderator
     * @return $this
     */
    public function addModerator($moderator)
    {
        return $this->getModeratorList()->add($moderator);
    }

    /**
     * @param  Collection|self[] $relatedResourceList
     * @return $this
     */
    public function setRelatedResourceList($relatedResourceList)
    {
        $this->relatedResourceList = $relatedResourceList;
        return $this;
    }

    /**
     * @return Collection|self[]
     */
    public function getRelatedResourceList()
    {
        return $this->relatedResourceList;
    }

    /**
     * @param  self  $resource
     * @return $this
     */
    public function addRelatedResource($resource)
    {
        $this->getRelatedResourceList()->add($resource);
        return $this;
    }

    /**
     * @param  Collection|RolePermission\Model[] $roleList
     *
     * @return $this
     */
    public function setRoleList($roleList)
    {
        $this->roleList = $roleList;
        return $this;
    }

    /**
     * @return Collection|RolePermission\Model[]
     */
    public function getRoleList()
    {
        return $this->roleList;
    }

    /**
     * @return bool
     */
    public function getBookingIsAuthorized(): bool
    {
        return $this->bookingIsAuthorized;
    }

    /**
     * @param bool $authorized
     * @return $this
     */
    public function setBookingIsAuthorized(bool $authorized): self
    {
        $this->bookingIsAuthorized = $authorized;

        return $this;
    }

    /**
     * @param  RolePermission\Model $role
     * @return $this
     */
    public function addRole($role)
    {
        $this->getRoleList()->add($role);
        return $this;
    }

    /**
     * Is this user allowed to moderate this item?
     *
     * They can if:
     * * They're a superuser
     * * They are a moderator of this item: @see isUserModerator
     *
     * @param User $user
     * @return bool
     */
    public function canUserModerateThisItem(User $user)
    {
        return $user->isSuperuser() || $this->isUserModerator($user);
    }

    /**
     * Is this user actually a moderator of this item?
     *
     * They are if:
     * * They have the resource booking service at level "moderate"
     * * They are a moderator for this item
     *
     * Note that superusers are not necessarily moderators of an item: however,
     * they may act as a moderator for any item: @see canUserModerateThisItem.
     *
     * @param User $user
     * @return bool
     */
    public function isUserModerator(User $user): bool
    {
        foreach ($user->getAccessList() as $access) {
            if (
                $access->getService() == UserAccess::SERVICE_RESOURCE_BOOKING
                && $access->getAccessLevel() == UserAccess::ACCESS_FULL
            ) {
                return true;
            }
        }

        // if there are no moderators, do not attempt to iterate through the null
        if (null !== $this->getModeratorList()) {
            foreach ($this->getModeratorList() as $moderator) {
                if ($moderator->getId() == $user->getId()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return array(
            'id'                   => $this->getId(),
            'name'                 => $this->getName(),
            'room_code'            => $this->getRoomCode(),
            'description'          => $this->getDescription(),
            'max_reservation_time' => $this->getMaxReservationTime(),
            'min_reservation_time' => $this->getMinReservationTime(),
            'current_term_restriction' => $this->getCurrentTermRestriction(),
            'min_lead_time'        => $this->getMinLeadTime(),
            'moderation_type'      => $this->getModerationType(),
            'moderator_list'       => $this->getModeratorList(),
            'category'             => $this->getCategory(),
            'bookingIsAuthorized'  => $this->getBookingIsAuthorized(),
        );
    }
}
