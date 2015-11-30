<?php
/**
 * Guardian
 *
 * Because I can't call it Parent
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User;
use Schoolbox\External\Normalise\User\Common\Account;
use Schoolbox\External\Normalise\User\Common\Address;
use Schoolbox\External\Normalise\User\Common\Phone;
use Schoolbox\Collection;

class Guardian extends Base {

    /* Contact
     */

    /**
     * Work phone
     *
     * @var Phone
     */
    protected $phoneWork = '';

    /**
     * Guardian type e.g. SC1, SC2
     *
     * @var string
     */
    protected $type = '';

    /**
     * Guardian role e.g. mother, father
     *
     * @var string
     */
    protected $role = '';

    /**
     * A collection of Student instances
     *
     * @var Collection|Student[]
     */
    protected $students = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->setup();
    }

    /**
     * Hook method
     */
    protected function setup() {
        $this->setAccount(new Account());
        $this->setPostalAddress(new Address());
        $this->setHomeAddress(new Address());
        $this->setEmail(new Collection());
        $this->setPhoneHome(new Phone());
        $this->setPhoneMobile(new Phone());
        $this->setPhoneWork(new Phone());
        $this->setCampus(new Collection());
        $this->setStudents(new Collection());
    }

    /**
     * Get the students' year(s) as a string
     *
     * @param string $separator
     * @return string
     */
    public function getYearAsString($separator = "\n") {
        $years = array();

        foreach($this->getStudents() as $currentStudent) {
            $years[] = $currentStudent->getYear()->getLevel();
        }

        return implode($separator, array_filter(array_unique($years)));
    }

    /**
     * Get the students' campus(es) as a string
     *
     * @param string $separator
     * @return string
     */
    public function getCampusAsString($separator = "\n") {
        $campuses = array();

        foreach($this->getCampus() as $currentCampus) {
            $campuses[] = $currentCampus->getName();
        }

        return implode($separator, array_filter(array_unique($campuses)));
    }

    /**
     * Add a student to the collection
     * This is a student belonging to this guardian
     *
     * @param Student $student
     * @return $this
     */
    public function addStudent(Student $student) {
        $this->getStudents()->add($student);

        return $this;
    }

    /* Getters/Setters
     */

    /**
     * Set phone work
     *
     * @param Phone $phoneWork
     * @return $this
     */
    public function setPhoneWork($phoneWork)
    {
        $this->phoneWork = $phoneWork;
        return $this;
    }

    /**
     * Get phone work
     *
     * @return Phone
     */
    public function getPhoneWork()
    {
        return $this->phoneWork;
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
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set role
     *
     * @param string $role
     * @return $this
     */
    public function setRole($role)
    {
        $this->role = $role;
        return $this;
    }

    /**
     * Get role
     *
     * @return string
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * Set students
     *
     * @param Collection|Student[] $students
     */
    private function setStudents($students)
    {
        $this->students = $students;
    }

    /**
     * Get students
     *
     * @return Collection|Student[]
     */
    public function getStudents()
    {
        return $this->students;
    }
}