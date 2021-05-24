<?php

/**
 * Model for resource role permissions.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Item\RolePermission;

use Schoolbox\ResourceBooking\Item;
use Schoolbox\Role;
use JsonSerializable;

class Model implements JsonSerializable
{

    /**
     * @var int
     */
    protected $id;

    /**
     * @var Role
     */
    protected $role;

    /**
     * @var Item\Model
     */
    protected $resource;

    /**
     * @var bool
     */
    protected $allowed;

    /**
     * @param  boolean $allowed
     * @return $this
     */
    public function setAllowed($allowed)
    {
        $this->allowed = $allowed;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getAllowed()
    {
        return $this->allowed;
    }

    /**
     * @param  int $id
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
     * @param  Role $role
     * @return $this
     */
    public function setRole($role)
    {
        $this->role = $role;
        return $this;
    }

    /**
     * @return Role
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *       which is a value of any type other than a resource.
     */
    public function jsonSerialize()
    {
        return array(
            'role'     => $this->role->getId(),
            'resource' => $this->resource->getId(),
            'allowed'  => $this->getAllowed(),
        );
    }
}
