<?php
/**
 * Implementation of our standard day event in Schoolbox.
 * Note this events can be campus, group or personal events.
 *
 * Other event systems also use psuedo versions of this event timetable.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\Timetable;

use Calendar\Event\Type\Day;

class Event extends Day\Event
{

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
     * @var bool $periodCancelled Whether the period is cancelled
     */
    protected $periodCancelled;

    /**
     * @var bool $hasAttendance If this class has attendance that can be marked
     */
    protected $hasAttendance = false;

    /**
     * A list of periods for which attendance may be marked for this timetabled
     * class.
     *
     * If this class spans multiple periods, then the first period will contain
     * the attendance periods for the entire span.
     *
     * @var array
     */
    protected $attendancePeriods = [];

    /**
     * The timetable day's file type.
     *
     * Another piece of what's required for rendering an attendance link now.
     *
     * @var string
     */
    private $fileType;

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
     * @return bool
     */
    public function getPeriodCancelled(): bool
    {
        return $this->periodCancelled;
    }

    /**
     * @param bool $periodCancelled
     * @return $this
     */
    public function setPeriodCancelled(bool $periodCancelled): self
    {
        $this->periodCancelled = $periodCancelled;
        return $this;
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


    /**
     * @return array
     */
    public function getAttendancePeriods(): array
    {
        return $this->attendancePeriods;
    }


    /**
     * @param array $attendancePeriods
     * @return static
     */
    public function setAttendancePeriods(array $attendancePeriods): self
    {
        $this->attendancePeriods = $attendancePeriods;
        return $this;
    }


    /**
     * @return string
     */
    public function getFileType(): string
    {
        return $this->fileType;
    }


    /**
     * @param string $fileType
     * @return static
     */
    public function setFileType(string $fileType): self
    {
        $this->fileType = $fileType;
        return $this;
    }


    /**
     * @return string[]
     */
    protected function getRenderClasses(): array
    {
        return [
            self::RENDER_FORMAT_JSON => Renderer\Javascript::class,
            self::RENDER_FORMAT_HTML => Renderer\Html::class,
            self::RENDER_FORMAT_VCAL => Renderer\VObject::class,
            self::RENDER_FORMAT_CSV  => Renderer\Csv::class,
        ];
    }
}
