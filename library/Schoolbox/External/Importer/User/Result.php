<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
     * @var UserRelation[]
     */
    protected $userRelations = [];

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
     * @return UserRelation
     */
    public function getUserRelations()
    {
        return $this->userRelations;
    }

    /**
     * @param  UserRelation $userRelations
     * @return $this
     */
    public function setUserRelations($userRelations)
    {
        $this->userRelations = $userRelations;
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
