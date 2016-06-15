<?php

namespace Schoolbox\SportsFixtures;

use \Schoolbox\Permission\Access\UserAccessMapper;
use \Schoolbox\User;

/**
 * Authenticator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Authenticator
{

    /** @var User */
    private $user;

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /**
     * Authenticator constructor.
     *
     * @param User $user
     * @param UserAccessMapper $userAccessMapper
     */
    public function __construct(User $user, UserAccessMapper $userAccessMapper)
    {
        $this->setUser($user);
        $this->setUserAccessMapper($userAccessMapper);
    }

    /**
     * Decide if the injected user was a Sports Administrator
     *
     * @return bool
     */
    public function isSportsAdministrator()
    {
        return $this->getUserAccessMapper()->findAllForUser($this->getUser())->filterBy("service", SPORTS_ADMINISTRATOR_SERVICE)->count() > 0;
    }

    /* Getters/Setters
     */

    /**
     * @return User
     */
    private function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return self
     */
    private function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return UserAccessMapper
     */
    public function getUserAccessMapper()
    {
        return $this->userAccessMapper;
    }

    /**
     * @param UserAccessMapper $userAccessMapper
     * @return self
     */
    public function setUserAccessMapper($userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;

        return $this;
    }
}