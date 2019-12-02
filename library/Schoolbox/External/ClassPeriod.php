<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\External;

use Schoolbox\User;

/**
 * Class ClassPeriod
 *
 * Class period object for the Schoolbox connector.
 *
 * @package Schoolbox\External
 */
class ClassPeriod
{

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
     * @var string|null
     */
    protected $teacherExternalId;


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
     * @param  string|null $teacherExternalId
     * @return $this
     */
    public function setTeacher($teacherExternalId) {
        $this->teacherExternalId = $teacherExternalId;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getTeacher() {
        return $this->teacherExternalId;
    }
}
