<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Permission\Access;


use Schoolbox\User;

class UserAccess
{

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for homepage $this->itemId.
     */
    const SERVICE_HOMEPAGE = 1;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the calendar for campus
     * $this->itemId.
     */
    const SERVICE_CALENDAR = 2;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the calendar for campus
     * $this->itemId.
     */
    const SERVICE_NEWS = 3;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for all homepages.
     *
     * @todo confirm if this is the case
     */
    const SERVICE_CMS = 5;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the leave management
     * system.
     */
    const SERVICE_HOUSEMASTER = 6;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the resource booking
     * system.
     */
    const SERVICE_RESOURCE_BOOKING = 7;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the curriculum
     * system.
     */
    const SERVICE_CURRICULUM = 8;

    /**
     * Value for $this->accessLevel:
     * $this->user is denied access to resource in $this->service.
     */
    const ACCESS_DENY = 0;

    /**
     * Value for $this->accessLevel:
     * $this->user has read access to resource in $this->service.
     */
    const ACCESS_READ = 1;

    /**
     * Value for $this->accessLevel:
     * $this->user has write access to resource in $this->service.
     */
    const ACCESS_WRITE = 2;

    /**
     * Value for $this->accessLevel:
     * $this->user has moderate access to resource in $this->service.
     */
    const ACCESS_FULL = 3;


    /** @var User */
    protected $user;

    /** @var int */
    protected $service;

    /** @var int */
    protected $sid;

    /** @var int */
    protected $accessLevel;

    /**
     * @param User $user
     * @return self
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }


    /**
     * @param int $service
     * @return self
     */
    public function setService($service)
    {
        $this->service = $service;
        return $this;
    }


    /**
     * @return int
     */
    public function getService()
    {
        return $this->service;
    }


    /**
     * @param int $sid
     * @return self
     */
    public function setServiceIdentifier($sid)
    {
        $this->sid = $sid;
        return $this;
    }


    /**
     * @return int
     */
    public function getServiceIdentifier()
    {
        return $this->sid;
    }


    /**
     * @param int $level
     * @return self
     */
    public function setAccessLevel($level)
    {
        $this->accessLevel = $level;
        return $this;
    }


    /**
     * @return int
     */
    public function getAccessLevel()
    {
        return $this->accessLevel;
    }


} 