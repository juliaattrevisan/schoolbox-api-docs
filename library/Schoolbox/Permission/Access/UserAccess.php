<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Permission\Access;


use Schoolbox\BaseModel;
use Schoolbox\User;

class UserAccess extends BaseModel
{

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for homepage $this->itemId.
     */
    public const SERVICE_HOMEPAGE = 1;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the calendar for campus
     * $this->itemId.
     */
    public const SERVICE_CALENDAR = 2;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the calendar for campus
     * $this->itemId.
     */
    public const SERVICE_NEWS = 3;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for all homepages.
     *
     * @todo confirm if this is the case
     */
    public const SERVICE_CMS = 5;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the leave management
     * system.
     */
    public const SERVICE_HOUSEMASTER = 6;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the resource booking
     * system.
     */
    public const SERVICE_RESOURCE_BOOKING = 7;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the curriculum
     * system.
     */
    public const SERVICE_CURRICULUM = 8;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the pastoral care
     * system.
     */
    public const SERVICE_PASTORAL = 9;

    /**
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the Sports Fixtures
     * system.
     */
    public const SERVICE_SPORTS_ADMINISTRATOR = 10;

    /**
     * Status of your acceptance into the group
     * 0 awaiting authorisation
     * 1 you're in
     */
    public const ACCESS_AWAITING_AUTHORISATION = 0;

    /**
     * Value for $this->accessLevel:
     * $this->user is denied access to resource in $this->service.
     */
    public const ACCESS_DENY = 0;

    /**
     * Value for $this->accessLevel:
     * $this->user has read access to resource in $this->service.
     */
    public const ACCESS_READ = 1;

    /**
     * Value for $this->accessLevel:
     * $this->user has write access to resource in $this->service.
     */
    public const ACCESS_WRITE = 2;

    /**
     * Value for $this->accessLevel:
     * $this->user has moderate access to resource in $this->service.
     */
    public const ACCESS_FULL = 3;


    /** @var User */
    protected $user;

    /** @var int */
    protected $service;

    /** @var int */
    protected $sid;

    /** @var int */
    protected $accessLevel;


    /**
     * Returns the set of all known access levels
     *
     * @return int[]
     */
    public static function getAccessLevels(): array
    {
        return [self::ACCESS_DENY, self::ACCESS_READ, self::ACCESS_WRITE, self::ACCESS_FULL,];
    }


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
