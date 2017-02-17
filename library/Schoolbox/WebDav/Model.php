<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\WebDav;

use Schoolbox\ISoftDeletable;
use Schoolbox\Campus;
use Schoolbox\Role;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model implements ISoftDeletable
{
    use ModelTrait\DeletedAtDate;

    /** @var int */
    protected $id;

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

    /** @var Campus */
    protected $campus;

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
}
