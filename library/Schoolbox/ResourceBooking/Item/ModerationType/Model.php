<?php

/**
 * Model for resource moderation types.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Item\ModerationType;

use JsonSerializable;

class Model implements JsonSerializable
{

    /**
     * Open resources may be booked by anyone, and are automatically confirmed.
     */
    const TYPE_OPEN = 1;

    /**
     * Moderated resources may be booked by anyone, but are not confirmed until
     * a moderator confirms the booking.
     */
    const TYPE_MODERATED = 2;

    /**
     * Managed resources may only be booked by moderators.
     */
    const TYPE_MANAGED = 3;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $description;

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
     * @param  string $name
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
     * @return bool
     */
    public function isOpen()
    {
        return $this->id == self::TYPE_OPEN;
    }

    /**
     * @return bool
     */
    public function isModerated()
    {
        return $this->id == self::TYPE_MODERATED;
    }

    /**
     * @return bool
     */
    public function isManaged()
    {
        return $this->id == self::TYPE_MANAGED;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return array(
            'id'          => $this->getId(),
            'name'        => $this->getName(),
            'isOpen'      => $this->isOpen(),
            'isModerated' => $this->isModerated(),
            'isManaged'   => $this->isManaged(),
        );
    }
}
