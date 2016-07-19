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
     * @var string Timetable external definition code
     */
    protected $timetableCode;

    /**
     * @var Class code as set in timetable
     */
    protected $code;

    /**
     * @var Period that this timetable event is in
     */
    protected $period;

    /**
     * @var Name of period that this class is in
     */
    protected $periodName;

    /**
     * @return Class
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param Class $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @return Period
     */
    public function getPeriod()
    {
        return $this->period;
    }

    /**
     * @param Period $period
     */
    public function setPeriod($period)
    {
        $this->period = $period;
    }

    /**
     * @return mixed
     */
    public function getPeriodName()
    {
        return $this->periodName;
    }

    /**
     * @param mixed $periodName
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



}
