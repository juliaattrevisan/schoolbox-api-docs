<?php
/**
 * Day
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise;

use Schoolbox\Collection;


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
     */
    public function setName($name)
    {
        $this->name = $name;
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
     */
    public function setNumber($number)
    {
        $this->number = $number;
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
     */
    public function setAllPeriods($allPeriods)
    {
        $this->allPeriods = $allPeriods;
    }
}