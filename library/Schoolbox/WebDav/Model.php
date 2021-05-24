<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\WebDav;

use Schoolbox\ISoftDeletable;
use Schoolbox\Campus;
use Schoolbox\Role;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /** @var string */
    protected $uri;

    /** @var string */
    protected $path;

    /** @var string */
    protected $usernamePrefix;

    /** @var string */
    protected $usernameSuffix;

    /** @var Role */
    protected $role;

    /** @var string */
    protected $roleType;

    /** @var Campus */
    protected $campus;

    /** @var bool */
    protected $enabled;

    /**
     * Does this WebDAV rule match the given user?
     *
     * In order to do so it must match at least one of:
     * * Role type
     * * Role
     * * Campus
     * Or all must be empty.
     *
     * @param User $user
     * @return bool
     */
    public function matches(User $user)
    {
        $isRoleTypeRule = !empty($this->getRoleType());
        $isRoleRule     = $this->getRole() instanceof Role;
        $isCampusRule   = $this->getCampus() instanceof Campus;
        $campusMatch    = false;
        $roleMatch      = false;
        $roleTypeMatch  = false;

        // This is a generic rule - it matches all users
        if (!($isRoleTypeRule || $isRoleRule || $isCampusRule)) {
            return true;
        }

        // Roletype match
        if ($isRoleTypeRule && $this->getRoleType() == $user->getRole()->getType()) {
            $roleTypeMatch = true;
        }

        // Role match
        if ($isRoleRule && $this->getRole()->getId() == $user->getRole()->getId()) {
            $roleMatch = true;
        }

        // Campus match
        if ($isCampusRule) {
            foreach ($user->getCampusCollection() as $campus) {
                if ($this->getCampus()->getId() == $campus->getId()) {
                    $campusMatch = true;
                }
            }
        }

        //All rules that are set are applied
        return ((!$isCampusRule || $campusMatch) && (!$isRoleRule || $roleMatch) && (!$isRoleTypeRule || $roleTypeMatch));
    }

    /**
     * @param string $uri
     * @return self
     */
    public function setUri($uri)
    {
        $this->uri = $uri;
        return $this;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @param string $path
     * @return self
     */
    public function setPath($path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $usernamePrefix
     * @return self
     */
    public function setUsernamePrefix($usernamePrefix)
    {
        $this->usernamePrefix = $usernamePrefix;
        return $this;
    }

    /**
     * @return string
     */
    public function getUsernamePrefix()
    {
        return $this->usernamePrefix;
    }

    /**
     * @param string $usernameSuffix
     * @return self
     */
    public function setUsernameSuffix($usernameSuffix)
    {
        $this->usernameSuffix = $usernameSuffix;
        return $this;
    }

    /**
     * @return string
     */
    public function getUsernameSuffix()
    {
        return $this->usernameSuffix;
    }

    /**
     * @param bool $enabled
     * @return self
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param Campus $campus
     * @return self
     */
    public function setCampus($campus)
    {
        $this->campus = $campus;
        return $this;
    }

    /**
     * @return Campus
     */
    public function getCampus()
    {
        return $this->campus;
    }

    /**
     * @param Role $role
     * @return self
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
     * @return string
     */
    public function getRoleType()
    {
        return $this->roleType;
    }

    /**
     * @param string $roleType
     * @return self
     */
    public function setRoleType($roleType)
    {
        $this->roleType = $roleType;
        return $this;
    }
}
