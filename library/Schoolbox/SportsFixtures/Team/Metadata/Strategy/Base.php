<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team\Metadata\Strategy;

use \Schoolbox\UserMapper;

class Base
{
    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Base constructor.
     */
    public function __construct()
    {
        $this->setUserMapper(UserMapper::getInstance());

        $this->setup();
    }

    /**
     * Hook method
     */
    protected function setup()
    {
    }

    /* Getters/Setters
     */

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param UserMapper $userMapper
     */
    protected function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }
}