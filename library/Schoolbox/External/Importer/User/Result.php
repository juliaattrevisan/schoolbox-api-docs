<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\User;

use Schoolbox\External\UserProfile;
use Schoolbox\External\UserRelation;
use Schoolbox\User as UserModel;

/**
 * A single result from a user import.
 *
 * @package Schoolbox\External\Importer\User
 */
class Result
{
    /**
     * @var UserModel
     */
    protected $user;

    /**
     * The user before any changes were made.
     *
     * @var UserModel
     */
    protected $originalUser;

    /**
     * @var UserModel[]
     */
    protected $children = [];

    /**
     * The user's children before any changes were made.
     *
     * @var UserModel[]
     */
    protected $originalChildren = [];

    /**
     * @var UserProfile
     */
    protected $userProfile = null;

    /**
     * The user profile before any changes were made.
     *
     * @var UserProfile
     */
    protected $originalUserProfile = null;

    /**
     * @var bool
     */
    protected $isUserDeleted = false;

    /**
     * @return UserModel
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param  UserModel $user
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return UserModel
     */
    public function getOriginalUser()
    {
        return $this->originalUser;
    }

    /**
     * @param  UserModel $originalUser
     * @return $this
     */
    public function setOriginalUser($originalUser)
    {
        $this->originalUser = $originalUser;
        return $this;
    }

    /**
     * @return UserModel[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param  UserModel[] $children
     * @return $this
     */
    public function setChildren($children)
    {
        $this->children = $children;
        return $this;
    }

    /**
     * @return UserModel[]
     */
    public function getOriginalChildren()
    {
        return $this->originalChildren;
    }

    /**
     * @param  UserModel[] $originalChildren
     * @return $this
     */
    public function setOriginalChildren($originalChildren)
    {
        $this->originalChildren = $originalChildren;
        return $this;
    }

    /**
     * @return UserProfile
     */
    public function getUserProfile()
    {
        return $this->userProfile;
    }

    /**
     * @param  UserProfile $userProfile
     * @return $this
     */
    public function setUserProfile($userProfile)
    {
        $this->userProfile = $userProfile;
        return $this;
    }

    /**
     * @return UserProfile
     */
    public function getOriginalUserProfile()
    {
        return $this->originalUserProfile;
    }

    /**
     * @param  UserProfile $originalUserProfile
     * @return $this
     */
    public function setOriginalUserProfile($originalUserProfile)
    {
        $this->originalUserProfile = $originalUserProfile;
        return $this;
    }

    /**
     * @return bool
     */
    public function isUserDeleted()
    {
        return $this->isUserDeleted;
    }

    /**
     * @param  bool  $isUserDeleted
     * @return $this
     */
    public function setIsUserDeleted($isUserDeleted)
    {
        $this->isUserDeleted = $isUserDeleted;
        return $this;
    }
}
