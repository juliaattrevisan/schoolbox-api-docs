<?php
/**
 * Timetable cycle object for Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
     * @var \Alaress_Date_Format
     */
    protected $start;

    /**
     * @var \Alaress_Date_Format
     */
    protected $finish;

    /**
     * The day number on which this timetable starts.
     *
     * @var int
     */
    protected $startDay = 1;

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
     * @var \Alaress_Date_Format
     */
    protected $reset;


    /**
     * This corresponds to luCampus.ext_timetable_code too (that's how they join up)
     *
     * @var string|null
     */
    private $externalTimetableCode;


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

    /**
     * @return string|null
     */
    public function getExternalTimetableCode(): ?string
    {
        return $this->externalTimetableCode;
    }

    /**
     * @param string|null $externalTimetableCode
     * @return $this
     */
    public function setExternalTimetableCode(?string $externalTimetableCode): self
    {
        $this->externalTimetableCode = $externalTimetableCode;
        return $this;
    }
}
