<?php

/**
 * Timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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

    /**
     * Add a Day instance to the timetable
     *
     * @param Day $day
     * @return $this
     */
    public function addDay(Day $day)
    {
        $this->getAllDays()->add($day);

        return $this;
    }

    /**
     * Decompile an NDS Timetable back into an array
     */
    public function decompile()
    {
        $timetable = [];

        foreach ($this->getAllDays() as $currentDay) {
            $dayIndex = $currentDay->getNumber();

            foreach ($currentDay->getAllPeriods() as $currentPeriod) {
                $periodIndex = $currentPeriod->getNumber() - 1;

                /* Initialise Period struct if empty
                 */
                if (!is_array($timetable[$periodIndex])) {
                    $timetable[$periodIndex] = $currentPeriod->decompile();
                }

                /* Decompile Classes
                 */
                foreach ($currentPeriod->getAllClasses() as $currentClass) {
                    $timetable[$periodIndex]['day'][$dayIndex][$currentClass->getCode()] = $currentClass->decompile();
                }
            }
        }

        return $timetable;
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
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
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
     * @return $this
     */
    public function setAllDays($allDays)
    {
        $this->allDays = $allDays;

        return $this;
    }
}
