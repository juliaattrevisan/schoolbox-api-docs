<?php

/**
 * Lesson
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\External\Normalise;

use Schoolbox\Collection;
use Schoolbox\Campus;

class Lesson
{

    /**
     * Lesson name
     *
     * @var string
     */
    private $name = '';

    /**
     * Lesson Code
     *
     * @var string
     */
    private $code = '';

    /**
     * The type of Lesson
     * a.k.a. FileType in Synergetic
     *
     * @var string
     */
    private $type = '';

    /**
     * The room in which this Lesson occurs
     *
     * @var string
     */
    private $room = '';

    /**
     * A campus for which this Lesson exists
     *
     * @var Campus
     */
    private $campus = null;

    /**
     * A Collection of Student instances who are participants
     * of this Lesson
     *
     * @var Collection
     */
    private $allStudents = null;

    /**
     * A Collection of Staff instances who are participants
     * of this Lesson
     *
     * @var Collection
     */
    private $allStaff = null;

    /**
     * Lesson constructor.
     */
    public function __construct()
    {
        $this->setAllStudents(new Collection());
        $this->setAllStaff(new Collection());
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
     * Get code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set code
     *
     * @param string $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set type
     *
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get room
     *
     * @return string
     */
    public function getRoom()
    {
        return $this->room;
    }

    /**
     * Set room
     *
     * @param string $room
     */
    public function setRoom($room)
    {
        $this->room = $room;
    }

    /**
     * Get campus
     *
     * @return Campus
     */
    public function getCampus()
    {
        return $this->campus;
    }

    /**
     * Set campus
     *
     * @param Campus $campus
     */
    public function setCampus($campus)
    {
        $this->campus = $campus;
    }

    /**
     * Get all students
     *
     * @return Collection
     */
    public function getAllStudents()
    {
        return $this->allStudents;
    }

    /**
     * Set all students
     *
     * @param Collection $allStudents
     */
    public function setAllStudents($allStudents)
    {
        $this->allStudents = $allStudents;
    }

    /**
     * Get all staff
     *
     * @return Collection
     */
    public function getAllStaff()
    {
        return $this->allStaff;
    }

    /**
     * Set all staff
     *
     * @param Collection $allStaff
     */
    public function setAllStaff($allStaff)
    {
        $this->allStaff = $allStaff;
    }
}