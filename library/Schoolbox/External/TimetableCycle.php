<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External;

/**
 * Class TimetableCycle
 *
 * Timetable cycle object for Schoolbox.
 *
 * @package Schoolbox\External
 */
class TimetableCycle
{

    /**
     * @var int
     */
    private $id;


    /**
     * @var string
     */
    private $title;


    /**
     * @var int
     */
    private $cycle_length;


    /**
     * @var int
     */
    private $day_length;


    /**
     * @var \Alaress_Date_Format
     */
    private $start;


    /**
     * @var \Alaress_Date_Format
     */
    private $finish;


    /**
     * The day number on which this timetable starts.
     *
     * @var int
     */
    private $startDay = 1;


    /**
     * This is an identifier for a third-party data set so you can manage your suds imports' data sets
     * You can use it to identify data sets from third party vendors like isams, kamar, etc.
     *
     * It used to correspond to luCampus.ext_timetable_code (that's how they joined up before)
     * but luCampus now directly associates with a cycle via luCampus.cycle_id, thus freeing up
     * this particular external timetable code's usage.
     *
     * @var string|null
     */
    private $cycleIdentifier;


    /**
     * @param  int   $cycle_length
     * @return $this
     */
    public function setCycleLength($cycle_length)
    {
        $this->cycle_length = $cycle_length;
        return $this;
    }


    /**
     * @return int
     */
    public function getCycleLength()
    {
        return $this->cycle_length;
    }


    /**
     * @param  int   $day_length
     * @return $this
     */
    public function setDayLength($day_length)
    {
        $this->day_length = $day_length;
        return $this;
    }


    /**
     * @return int
     */
    public function getDayLength()
    {
        return $this->day_length;
    }


    /**
     * @param  int   $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param  string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }


    /**
     * @param  \Alaress_Date_Format $finish
     * @return $this
     */
    public function setFinish($finish)
    {
        $this->finish = $finish;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getFinish()
    {
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
    public function setStart($start)
    {
        $this->start = $start;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getStart()
    {
        return $this->start;
    }


    /**
     * @return string|null
     */
    public function getCycleIdentifier(): ?string
    {
        return $this->cycleIdentifier;
    }


    /**
     * @param string|null $cycleIdentifier
     * @return $this
     */
    public function setCycleIdentifier(?string $cycleIdentifier): self
    {
        $this->cycleIdentifier = $cycleIdentifier;
        return $this;
    }
}
