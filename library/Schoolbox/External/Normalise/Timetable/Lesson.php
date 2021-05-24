<?php

/**
 * Lesson (aka. Class- because 'class' is a keyword... #sadface)
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\Timetable;

use Schoolbox\Collection;
use Schoolbox\Campus;

class Lesson
{
    /**
     * Unique identifier
     *
     * @var int
     */
    private $id = '';

    /**
     * Lesson/class name
     *
     * @var string
     */
    private $name = '';

    /**
     * Lesson/class code
     *
     * @var string
     */
    private $code = '';

    /**
     * The subject this lesson relates to
     *
     * @var Subject $subject
     */
    private $subject;

    /**
     * The type of Lesson (aka. FileType)
     *
     * @var string
     */
    private $type = '';

    /**
     * The room in which this class occurs
     *
     * @var string
     */
    private $room = '';

    /**
     * A campus for which this class exists
     *
     * @var Campus
     */
    private $campus = null;

    /**
     * A collection of students who are participants
     *
     * @var Collection
     */
    private $allStudents = null;

    /**
     * A collection of staff who are participants
     *
     * @var Collection
     */
    private $allStaff = null;

    /**
     * Constructor
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
        } else {
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
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set id
     *
     * @param int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

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
     * Get subject
     *
     * @return Subject
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Set subject
     *
     * @param Subject $subject
     * @return $this
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;

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
