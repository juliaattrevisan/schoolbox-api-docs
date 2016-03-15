<?php
/**
 * Timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\Timetable;

use Schoolbox\Collection;
use Schoolbox\User;

class Timetable
{

    /**
     * A Schoolbox\User instance to whom this
     * Timetable instance belongs - could be either
     * student or staff
     *
     * @var User
     */
    private $user = null;

    /**
     * A Collection of Day instances
     *
     * @var Collection
     */
    private $allDays = null;

    /**
     * Timetable constructor.
     */
    public function __construct()
    {
        $this->setAllDays(new Collection());
    }

    /* Getters/Setters
     */

    /**
     * Get user
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set user
     *
     * @param User $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Get all days
     *
     * @return Collection
     */
    public function getAllDays()
    {
        return $this->allDays;
    }

    /**
     * Set all days
     *
     * @param Collection $allDays
     */
    public function setAllDays($allDays)
    {
        $this->allDays = $allDays;
    }
}