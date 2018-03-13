<?php
/**
 * Implementation of our standard day event in Schoolbox.
 * Note this events can be campus, group or personal events.
 *
 * Other event systems also use psuedo versions of this event timetable.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Calendar\Event\Type\Timetable;

use Calendar\Event\Type\Day\Event as DayEvent;
use Calendar\Event\Type\EventInterface;


class Event extends DayEvent implements EventInterface {

    /**
     * Renderer classes
     */
    protected $renderClasses = array(   'javascript'    => Renderer\Javascript::class,
                                        'html'          => Renderer\Html::class,
                                        'vcal'          => Renderer\VObject::class,
                                        'csv'           => Renderer\Csv::class,
    );

    /**
     * @var string $timetableCode Timetable external definition code
     */
    protected $timetableCode;

    /**
     * @var string $code Class code as set in timetable
     */
    protected $code;

    /**
     * @var string $campus Campus
     */
    protected $campus;

    /**
     * @var string $period Period that this timetable event is in
     */
    protected $period;

    /**
     * @var string $periodName Name of period that this class is in
     */
    protected $periodName;

    /**
     * @var bool $hasAttendance If this class has attendance that can be marked
     */
    protected $hasAttendance = false;

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getCampus()
    {
        return $this->campus;
    }

    /**
     * @param string $campus
     */
    public function setCampus($campus)
    {
        $this->campus = $campus;
    }

    /**
     * @return string
     */
    public function getPeriod()
    {
        return $this->period;
    }

    /**
     * @param string $period
     */
    public function setPeriod($period)
    {
        $this->period = $period;
    }

    /**
     * @return string
     */
    public function getPeriodName()
    {
        return $this->periodName;
    }

    /**
     * @param string $periodName
     */
    public function setPeriodName($periodName)
    {
        $this->periodName = $periodName;
    }

    /**
     * @return string
     */
    public function getTimetableCode()
    {
        return $this->timetableCode;
    }

    /**
     * @param string $timetable
     */
    public function setTimetableCode($timetable)
    {
        $this->timetableCode = $timetable;
    }

    /**
     * @return bool
     */
    public function hasAttendance(): bool
    {
        return $this->hasAttendance;
    }

    /**
     * @param bool $hasAttendance
     */
    public function setHasAttendance(bool $hasAttendance): void
    {
        $this->hasAttendance = $hasAttendance;
    }



}
