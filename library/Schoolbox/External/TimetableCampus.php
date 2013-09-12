<?php
/**
 * Timetable campus object for Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External;

use Schoolbox\Campus;

class TimetableCampus {

    /**
     * @var int
     */
    protected $id;

    /**
     * @var TimetableCycle
     */
    protected $cycle;

    /**
     * @var Campus
     */
    protected $campus;

    /**
     * @var \Alaress_Date_Format
     */
    protected $start;

    /**
     * @var \Alaress_Date_Format
     */
    protected $reset;

    /**
     * @var \Alaress_Date_Format
     */
    protected $finish;

    /**
     * @var int
     */
    protected $offset;

    /**
     * @param  \Schoolbox\Campus $campus
     * @return $this
     */
    public function setCampus($campus) {
        $this->campus = $campus;
        return $this;
    }

    /**
     * @return \Schoolbox\Campus
     */
    public function getCampus() {
        return $this->campus;
    }

    /**
     * @param  \Schoolbox\External\TimetableCycle $cycle
     * @return $this
     */
    public function setCycle($cycle) {
        $this->cycle = $cycle;
        return $this;
    }

    /**
     * @return \Schoolbox\External\TimetableCycle
     */
    public function getCycle() {
        return $this->cycle;
    }

    /**
     * @param  \Alaress_Date_Format $finish
     * @return $this
     */
    public function setFinish($finish) {
        $this->finish = $finish;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getFinish() {
        return $this->finish;
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
     * @param  int   $offset
     * @return $this
     */
    public function setOffset($offset) {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @return int
     */
    public function getOffset() {
        return $this->offset;
    }

    /**
     * @param  \Alaress_Date_Format $reset
     * @return $this
     */
    public function setReset($reset) {
        $this->reset = $reset;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getReset() {
        return $this->reset;
    }

    /**
     * @param  \Alaress_Date_Format $start
     * @return $this
     */
    public function setStart($start) {
        $this->start = $start;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getStart() {
        return $this->start;
    }

}
