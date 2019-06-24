<?php
/**
 * Contains information about the type of group that a folder represents
 * Folders can have groups of type, free, moderator or private.
 * A folder is not a group unless this is set
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Resource;

class Group {

    /**
     * The three types of groups you can join:
     *
     * PRIVATE (invite only)
     * MODERATED (assigned, by superusers)
     * FREE (anyone can come and go)
     */
    public const PRIVATE_GROUP = 1;
    public const MODERATED_GROUP = 2;
    public const FREE_GROUP = 3;

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
    public function setCategory($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return Folder\Model
     */
    public function getCategory(): Folder\Model
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
}
