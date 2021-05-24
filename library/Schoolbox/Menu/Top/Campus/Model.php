<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Menu\Top\Campus;

use Schoolbox\ISoftDeletable;

class Model
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $menuTopId;

    /** @var int */
    protected $campusId;

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
}
