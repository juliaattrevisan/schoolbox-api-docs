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
use Schoolbox\SportsFixtures\Match\Model as Match;

class Base
{
    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var Match
     */
    protected $match;
    
    /**
     * Base constructor.
     *
     * @param Match $match
     */
    public function __construct(Match $match)
    {
        $this->setUserMapper(UserMapper::getInstance());
        $this->setMatch($match);

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

    /**
     * @return mixed
     */
    protected function getMatch()
    {
        return $this->match;
    }

    /**
     * @param mixed $match
     */
    protected function setMatch($match)
    {
        $this->match = $match;
    }
}