<?php
/**
 * Class period object for the Schoolbox connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External;

use Schoolbox\User;

class ClassPeriod {

    /**
     * @var int
     */
    protected $id;

    /**
     * @var ClassEntry
     */
    protected $class;

    /**
     * @var TimetablePeriod
     */
    protected $period;

    /**
     * @var User
     */
    protected $teacher;

    /**
     * @var string
     */
    protected $room;

    /**
     * @param  \Schoolbox\External\ClassEntry $class
     * @return $this
     */
    public function setClass($class) {
        $this->class = $class;
        return $this;
    }

    /**
     * @return \Schoolbox\External\ClassEntry
     */
    public function getClass() {
        return $this->class;
    }

    /**
     * @param  int   $id
     * @return $this
     */
    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param  \Schoolbox\External\TimetablePeriod $period
     * @return $this
     */
    public function setPeriod($period) {
        $this->period = $period;
        return $this;
    }

    /**
     * @return \Schoolbox\External\TimetablePeriod
     */
    public function getPeriod() {
        return $this->period;
    }

    /**
     * @param  string $room
     * @return $this
     */
    public function setRoom($room) {
        $this->room = $room;
        return $this;
    }

    /**
     * @return string
     */
    public function getRoom() {
        return $this->room;
    }

    /**
     * @param  \Schoolbox\User $teacher
     * @return $this
     */
    public function setTeacher($teacher) {
        $this->teacher = $teacher;
        return $this;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getTeacher() {
        return $this->teacher;
    }

}
