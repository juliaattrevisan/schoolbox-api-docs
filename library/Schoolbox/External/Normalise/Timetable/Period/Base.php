<?php

/**
 * Base
 *
 * Basic information about a Period
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\External\Normalise\Timetable\Period;

use Schoolbox\Collection;
use Schoolbox\External\Normalise\Timetable\Lesson;

abstract class Base
{

    /**
     * Period name
     * e.g. "Homeroom Assembly"
     *
     * @var string
     */
    protected $name = '';

    /**
     * Period number
     * e.g. "1"
     *
     * @var int
     */
    protected $number = '';

    /**
     * Start datetime
     * Sometimes contains a date, but should always contain a time
     *
     * @var string;
     */
    protected $start = null;

    /**
     * End datetime
     * Sometimes contains a date, but should always contain a time
     *
     * @var string;
     */
    protected $end = null;

    /**
     * A Collection of Lesson instances occurring in this Period
     *
     * @var Collection
     */
    protected $allClasses = null;

    /**
     * Determine whether this instance is a Teaching
     * Period (or a Non-Teaching Period)
     *
     * @var bool
     */
    protected $isTeaching = true;

    /**
     * Base constructor.
     */
    public function __construct()
    {
        $this->setAllClasses(new Collection());
    }

    /**
     * Add a Lesson instance
     *
     * @param Lesson $class
     * @return $this
     */
    public function addClass(Lesson $class)
    {
        $this->getAllClasses()->add($class);

        return $this;
    }

    /**
     * This is a Teaching period
     *
     * @return bool
     */
    public function isTeaching()
    {
        return $this->isTeaching;
    }

    /**
     * Return ancient array format
     *
     * @return array
     */
    public function decompile()
    {
        return [
            'periodName'     => $this->getName(),
            'periodStart'    => $this->getStart(),
            'periodEnd'      => $this->getEnd(),
            'periodNumber'   => $this->getNumber(),
            'periodSequence' => $this->isTeaching() ? 1 : 2,
            'day'            => []
        ];
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
     * @return string
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Set number
     *
     * @param string $number
     * @return $this
     */
    public function setNumber($number)
    {
        $this->number = $number;

        return $this;
    }

    /**
     * Get start
     *
     * @return string
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Set start
     *
     * @param string $start
     * @return $this
     */
    public function setStart($start)
    {
        $this->start = $start;

        return $this;
    }

    /**
     * Get end
     *
     * @return string
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * Set end
     *
     * @param string $end
     * @return $this
     */
    public function setEnd($end)
    {
        $this->end = $end;

        return $this;
    }

    /**
     * Get all classes
     *
     * @return Collection
     */
    public function getAllClasses()
    {
        return $this->allClasses;
    }

    /**
     * Set all classes
     *
     * @param Collection $allClasses
     * @return $this
     */
    public function setAllClasses($allClasses)
    {
        $this->allClasses = $allClasses;

        return $this;
    }
}