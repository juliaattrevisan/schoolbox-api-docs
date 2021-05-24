<?php

/**
 * Timetable period object for Schoolbox "external" connector
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External;

class TimetablePeriod
{

    /**
     * @var int
     */
    protected $id;

    /**
     * @var TimetableCycle
     */
    protected $cycle;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var int
     */
    protected $day;

    /**
     * @var int
     */
    protected $sequence;

    /**
     * @var \Alaress_Date_Format
     */
    protected $start;

    /**
     * @var \Alaress_Date_Format
     */
    protected $finish;

    /**
     * @var bool
     */
    protected $usable;

    /**
     * @param  \Schoolbox\External\TimetableCycle $cycle
     * @return $this
     */
    public function setCycle($cycle)
    {
        $this->cycle = $cycle;
        return $this;
    }

    /**
     * @return \Schoolbox\External\TimetableCycle
     */
    public function getCycle()
    {
        return $this->cycle;
    }

    /**
     * @param  int   $day
     * @return $this
     */
    public function setDay($day)
    {
        $this->day = $day;
        return $this;
    }

    /**
     * @return int
     */
    public function getDay()
    {
        return $this->day;
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
     * @param  int   $sequence
     * @return $this
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
        return $this;
    }

    /**
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
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
     * @param  bool  $usable
     * @return $this
     */
    public function setUsable($usable)
    {
        $this->usable = $usable;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getUsable()
    {
        return $this->usable;
    }
}
