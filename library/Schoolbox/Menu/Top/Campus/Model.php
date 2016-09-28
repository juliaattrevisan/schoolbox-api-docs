<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Menu\Top\Campus;

use Schoolbox\ISoftDeletable;

class Model implements ISoftDeletable
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $menuTopId;

    /** @var int */
    protected $campusId;

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
     * @param int $menuTopId
     * @return self
     */
    public function setMenuTopId($menuTopId)
    {
        $this->menuTopId = $menuTopId;
        return $this;
    }

    /**
     * @return int
     */
    public function getMenuTopId()
    {
        return $this->menuTopId;
    }

    /**
     * @param int $campusId
     * @return self
     */
    public function setCampusId($campusId)
    {
        $this->campusId = $campusId;
        return $this;
    }

    /**
     * @return int
     */
    public function getCampusId()
    {
        return $this->campusId;
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