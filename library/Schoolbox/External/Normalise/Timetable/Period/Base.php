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
     * Determine which type of Period instance this was
     *
     * @return mixed
     */
    public function findType()
    {
        return array_pop(explode('\\', get_class($this)));
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