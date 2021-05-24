<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Menu\Side\Role;

use Schoolbox\ISoftDeletable;

class Model
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $menuSideId;

    /** @var int */
    protected $roleId;

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
     * @param int $roleId
     * @return self
     */
    public function setRoleId($roleId)
    {
        $this->roleId = $roleId;
        return $this;
    }

    /**
     * @return int
     */
    public function getRoleId()
    {
        return $this->roleId;
    }
}
