<?php

/**
 * Lesson
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\External\Normalise\Timetable;

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

    /**
     * Return ancient array format
     *
     * @return array
     */
    public function decompile()
    {
        $classInfo = [
            'description' => $this->getName(),
            'code' => $this->getCode(),
            'campus' => $this->getCampus()->getExtTimetableCode(),
            'room' => $this->getRoom(),
        ];

        /* Only include Staff members if they exist
         * For classes with multiple teachers, just choose first()
         */
        if ($this->getAllStaff()->count()) {
            $staffMember = $this->getAllStaff()->first();

            $staffInfo = [
                'staffID'        => $staffMember->getId(),
                'staffFirstname' => $staffMember->getPreferredName(),
                'staffSurname'   => $staffMember->getLastname(),
                'staffTitle'     => $staffMember->getTitle()
            ];
        }
        else {
            $staffInfo = [
                'staffID'        => '',
                'staffFirstname' => '',
                'staffSurname'   => '',
                'staffTitle'     => ''
            ];
        }

        return array_merge($classInfo, $staffInfo);
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
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
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
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
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
     * @return $this
     */
    public function setRoom($room)
    {
        $this->room = $room;

        return $this;
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
     * @return $this
     */
    public function setCampus($campus)
    {
        $this->campus = $campus;

        return $this;
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
     * @return $this
     */
    public function setAllStudents($allStudents)
    {
        $this->allStudents = $allStudents;

        return $this;
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
     * @return $this
     */
    public function setAllStaff($allStaff)
    {
        $this->allStaff = $allStaff;

        return $this;
    }
}