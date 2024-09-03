<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Permission\Access;

use Schoolbox\IModel;
use Schoolbox\Mapper\Traits\Model\CreateCollection;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\User;

class UserAccess implements IModel
{
    use CreateCollection;
    use CreatedAtDate;

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
     * Value for $this->service:
     * $this->user has $this->accessLevel permission for the badge
     * system.
     */
    public const SERVICE_BADGE = 11;

    /**
     * Services that do not have have service identifiers (sid) use this value
     * (This is enforced at the DB level by not permitted nulls, and defaulting to '0)
     */
    public const SID_NONE = 0;

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

    public const MEMBERSHIP_ADMIN = 'admin';
    public const MEMBERSHIP_WRITE = 'write';
    public const MEMBERSHIP_READ  = 'read';
    public const MEMBERSHIP_NONE  = 'none';

    public const ACCESS_NAME_TO_LEVEL = [
        self::MEMBERSHIP_NONE  => self::ACCESS_DENY,
        self::MEMBERSHIP_READ  => self::ACCESS_READ,
        self::MEMBERSHIP_WRITE => self::ACCESS_WRITE,
        self::MEMBERSHIP_ADMIN => self::ACCESS_FULL,
    ];

    public const ACCESS_LEVEL_TO_NAME = [
        self::ACCESS_DENY  => self::MEMBERSHIP_NONE,
        self::ACCESS_READ  => self::MEMBERSHIP_READ,
        self::ACCESS_WRITE => self::MEMBERSHIP_WRITE,
        self::ACCESS_FULL  => self::MEMBERSHIP_ADMIN,
    ];

    /** @var User */
    protected $user;

    /** @var int */
    protected $service;

    /** @var int */
    protected $sid;

    /** @var int */
    protected $accessLevel;

    /** @var bool */
    protected bool $favourite = false;


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
     * Return all possible services
     *
     * @return int[]
     */
    public static function allServices(): array
    {
        return [
            self::SERVICE_HOMEPAGE,
            self::SERVICE_CALENDAR,
            self::SERVICE_NEWS,
            self::SERVICE_CMS,
            self::SERVICE_RESOURCE_BOOKING,
            self::SERVICE_CURRICULUM,
            self::SERVICE_PASTORAL,
            self::SERVICE_SPORTS_ADMINISTRATOR,
        ];
    }


    /**
     * Returns the services that require an identifier
     *
     * @return int[]
     */
    public static function requiresServiceIdentifier(): array
    {
        return [
            self::SERVICE_HOMEPAGE,
            self::SERVICE_CALENDAR,
        ];
    }


    /**
     * Returns the services that should not have an identifier
     *
     * @return int[]
     */
    public static function doesNotRequireServiceIdentifier(): array
    {
        return \array_diff(self::allServices(), self::requiresServiceIdentifier());
    }

    /**
     * @param User $user
     * @return static
     */
    public function setUser($user): self
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
     * @return static
     */
    public function setService($service): self
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
     * @return static
     */
    public function setServiceIdentifier($sid): self
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
     * @return static
     */
    public function setAccessLevel($level): self
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

    /**
     * @param bool $favourite
     * @return static
     */
    public function setFavourite(bool $favourite): self
    {
        $this->favourite = $favourite;
        return $this;
    }

    /**
     * @return bool
     */
    public function getFavourite(): bool
    {
        return $this->favourite;
    }
}
