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
