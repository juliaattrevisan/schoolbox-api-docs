<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Log;

use Schoolbox\Resource\Storage;

class Model
{
    /** @var int */
    protected $id;

    /** @var string */
    protected $roleType;

    /** @var bool */
    protected $dry;

    /** @var int */
    protected $usersCreated;

    /** @var int */
    protected $usersUpdated;

    /** @var int */
    protected $usersSkipped;

    /** @var Storage\Model */
    protected $storage;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return string
     */
    public function getRoleType()
    {
        return $this->roleType;
    }


    /**
     * @param string $roleType
     * @return static
     */
    public function setRoleType($roleType)
    {
        $this->roleType = $roleType;
        return $this;
    }


    /**
     * @return boolean
     */
    public function isDry()
    {
        return $this->dry;
    }


    /**
     * @deprecated Mapper-use only
     * @return bool
     */
    public function getDry()
    {
        return $this->isDry();
    }


    /**
     * @param boolean $dry
     * @return static
     */
    public function setDry($dry)
    {
        $this->dry = $dry;
        return $this;
    }


    /**
     * @return int
     */
    public function getUsersCreated()
    {
        return $this->usersCreated;
    }


    /**
     * @param int $usersCreated
     * @return static
     */
    public function setUsersCreated($usersCreated)
    {
        $this->usersCreated = $usersCreated;
        return $this;
    }


    /**
     * @return int
     */
    public function getUsersUpdated()
    {
        return $this->usersUpdated;
    }


    /**
     * @param int $usersUpdated
     * @return static
     */
    public function setUsersUpdated($usersUpdated)
    {
        $this->usersUpdated = $usersUpdated;
        return $this;
    }


    /**
     * @return int
     */
    public function getUsersSkipped()
    {
        return $this->usersSkipped;
    }


    /**
     * @param int $usersSkipped
     * @return static
     */
    public function setUsersSkipped($usersSkipped)
    {
        $this->usersSkipped = $usersSkipped;
        return $this;
    }


    /**
     * @return Storage\Model
     */
    public function getStorage()
    {
        return $this->storage;
    }


    /**
     * @param Storage\Model $storage
     * @return static
     */
    public function setStorage($storage)
    {
        $this->storage = $storage;
        return $this;
    }

}
