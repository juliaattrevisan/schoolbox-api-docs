<?php

/**
 * Day
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\Timetable;

use Schoolbox\Collection;
use Schoolbox\External\Normalise\Timetable\Period\Base as PeriodBase;

class Day
{
    /**
     * Day Name
     * e.g. "Friday B"
     *
     * @var string
     */
    private $name = '';

    /**
     * Day Number
     * e.g. "1"
     *
     * @var int
     */
    private $number = '';

    /**
     * A Collection of Teaching/NonTeaching Period instances
     *
     * @var Collection
     */
    private $allPeriods = null;

    /**
     * Day constructor.
     */
    public function __construct()
    {
        $this->setAllPeriods(new Collection());
    }

    /**
     * Add a Period instance
     *
     * @param PeriodBase $period
     * @return $this
     */
    public function addPeriod(PeriodBase $period)
    {
        $this->getAllPeriods()->add($period);

        return $this;
    }

    /* Getters/Setters
     */

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get number
     *
     * @return int
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Set number
     *
     * @param int $number
     * @return $this
     */
    public function setNumber($number)
    {
        $this->number = $number;

        return $this;
    }

    /**
     * Get all periods
     *
     * @return Collection
     */
    public function getAllPeriods()
    {
        return $this->allPeriods;
    }

    /**
     * Set all periods
     *
     * @param Collection $allPeriods
     * @return $this
     */
    public function setAllPeriods($allPeriods)
    {
        $this->allPeriods = $allPeriods;

        return $this;
    }
}
