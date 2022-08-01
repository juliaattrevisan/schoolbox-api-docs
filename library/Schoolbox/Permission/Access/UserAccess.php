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
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class UserAccess implements IModel
{
    use CreateCollection;

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
    public const ACCESS_FULL      = 3;

    public const MEMBERSHIP_WRITE = 'write';
    public const MEMBERSHIP_NONE  = 'none';
    public const MEMBERSHIP_READ  = 'read';
    public const MEMBERSHIP_ADMIN = 'admin';

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

    /** @var Folder|null folder of the sid */
    private ?Folder $folder = null;

    /**
     * This is to present notification actually set in msg_reroute_override
     * @var bool
     */
    private bool $following = false;

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
            self::SERVICE_HOUSEMASTER,
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
            self::SERVICE_HOUSEMASTER,
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

    /**
     * @param bool $favourite
     * @return self
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

    /**
     * @return Folder|null
     */
    public function getFolder(): ?Folder
    {
        return $this->folder;
    }

    /**
     * @param Folder $folder
     * @return $this
     */
    public function setFolder(Folder $folder): self
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return bool
     */
    public function getFollowing(): bool
    {
        return $this->following;
    }

    /**
     * @param bool $following
     * @return $this
     */
    public function setFollowing(bool $following): self
    {
        $this->following = $following;
        return $this;
    }
}
