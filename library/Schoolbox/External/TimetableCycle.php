<?php
/**
 * Timetable cycle object for Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External;

class TimetableCycle {

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var int
     */
    protected $cycle_length;

    /**
     * @var int
     */
    protected $day_length;

    /**
     * @var bool
     */
    protected $regular;

    /**
     * @param  int   $cycle_length
     * @return $this
     */
    public function setCycleLength($cycle_length) {
        $this->cycle_length = $cycle_length;
        return $this;
    }

    /**
     * @return int
     */
    public function getCycleLength() {
        return $this->cycle_length;
    }

    /**
     * @param  int   $day_length
     * @return $this
     */
    public function setDayLength($day_length) {
        $this->day_length = $day_length;
        return $this;
    }

    /**
     * @return int
     */
    public function getDayLength() {
        return $this->day_length;
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
     * @param  boolean $regular
     * @return $this
     */
    public function setRegular($regular) {
        $this->regular = $regular;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getRegular() {
        return $this->regular;
    }

    /**
     * @param  string $title
     * @return $this
     */
    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle() {
        return $this->title;
    }

}
