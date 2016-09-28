<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Menu\Side\Group;

use Schoolbox\ISoftDeletable;

class Model implements ISoftDeletable
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $menuSideId;

    /** @var int */
    protected $groupId;

    /** @var \Alaress_Date_Format */
    protected $deletedAt;

    /**
     * @param int $id
     * @return self
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
     * @param int $menuSideId
     * @return self
     */
    public function setMenuSideId($menuSideId)
    {
        $this->menuSideId = $menuSideId;
        return $this;
    }

    /**
     * @return int
     */
    public function getMenuSideId()
    {
        return $this->menuSideId;
    }

    /**
     * @param int $groupId
     * @return self
     */
    public function setGroupId($groupId)
    {
        $this->groupId = $groupId;
        return $this;
    }

    /**
     * @return int
     */
    public function getGroupId()
    {
        return $this->groupId;
    }

    /**
     * @param \Alaress_Date_Format $deletedAt
     * @return self
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $deletedAt
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted()
    {
        return !$this->getDeletedAt() instanceof \Alaress_Date_NullDate;
    }
}