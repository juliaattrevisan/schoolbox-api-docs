<?php

/**
 * Contains information about the type of group that a folder represents
 * Folders can have groups of type, free, moderator or private.
 * A folder is not a group unless this is set
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Resource;

class Group
{
    /**
     * The three types of groups you can join:
     *
     * PRIVATE (invite only)
     * MODERATED (assigned, by group admin)
     * FREE (anyone can come and go)
     */
    public const PRIVATE_GROUP   = 1;
    public const MODERATED_GROUP = 2;
    public const FREE_GROUP      = 3;

    public const PRIVATE_TYPE_NAME   = 'private';
    public const MODERATED_TYPE_NAME = 'moderated';
    public const FREE_TYPE_NAME      = 'free';

    public const TYPE_NAME_TO_VAL = [
        self::PRIVATE_TYPE_NAME   => self::PRIVATE_GROUP,
        self::MODERATED_TYPE_NAME => self::MODERATED_GROUP,
        self::FREE_TYPE_NAME      => self::FREE_GROUP,
    ];

    public const TYPE_VAL_TO_NAME = [
        self::PRIVATE_GROUP   => self::PRIVATE_TYPE_NAME,
        self::MODERATED_GROUP => self::MODERATED_TYPE_NAME,
        self::FREE_GROUP      => self::FREE_TYPE_NAME,
    ];

    /**
     * The type of group
     *
     * @var int
     */
    protected $type;

    /**
     * @var Folder\Model
     */
    protected $folder;

    /**
     * @var bool
     */
    protected $forceFollow;

    /**
     * This is to present notification actually set in msg_reroute_override
     * @var bool
     */
    private bool $notificationDisabled = false;

    /**
     * Test if group is private
     *
     * @return bool
     */
    public function isPrivate(): bool
    {
        return $this->getType() === self::PRIVATE_GROUP;
    }

    /**
     * Test if group is moderated
     *
     * @return bool
     */
    public function isModerated(): bool
    {
        return $this->getType() === self::MODERATED_GROUP;
    }

    /**
     * Test if group is free
     *
     * @return bool
     */
    public function isFree(): bool
    {
        return $this->getType() === self::FREE_GROUP;
    }

    /**
     * @param int $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }


    /**
     * @param Folder\Model $folder
     * @return $this
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return Folder\Model
     */
    public function getFolder(): Folder\Model
    {
        return $this->folder;
    }


    /**
     * @param boolean $forceFollow
     * @return self
     */
    public function setForceFollow($forceFollow): Group
    {
        $this->forceFollow = $forceFollow;
        return $this;
    }


    /**
     * @return boolean
     */
    public function getForceFollow(): bool
    {
        return $this->forceFollow;
    }

    /**
     * get the text representaion of the group type (public, private, moderated)
     * @return string
     */
    public function getTypeString(): string
    {
        return self::TYPE_VAL_TO_NAME[$this->type] ?? '';
    }

    /**
     * @param bool $notificationDisabled
     * @return self
     */
    public function setNotificationDisabled(bool $notificationDisabled): self
    {
        $this->notificationDisabled = $notificationDisabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function getNotificationDisabled(): bool
    {
        return $this->notificationDisabled;
    }
}
