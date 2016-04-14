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
     * The number of days to offset the timetable start day by.
     * (starts from 1.)
     *
     * @deprecated Use $this->startDay
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * The day number on which this timetable starts.
     *
     * @var int
     */
    protected $startDay = 1;

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
     * @deprecated Use $this->setStartDay()
     *
     * @param  int   $offset
     * @return $this
     */
    public function setOffset($offset) {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @deprecated Use $this->getStartDay()
     *
     * @return int
     */
    public function getOffset() {
        return $this->offset;
    }

    /**
     * @return int
     */
    public function getStartDay()
    {
        return $this->startDay;
    }

    /**
     * @param  int $startDay
     * @return $this
     */
    public function setStartDay($startDay)
    {
        $this->startDay = $startDay;
        return $this;
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
