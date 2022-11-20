<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\SudsTimetable;

use Calendar\Event\Type\Day;

/**
 * This data is rendered by a Renderer class to display the data
 * in a variety of formats
 */
class Event extends Day\Event
{
    /**
     * @var string|null
     */
    private ?string $code;


    /**
     * @var string|null
     */
    private ?string $day;


    /**
     * @var string|null
     */
    private ?string $period;


    /**
     * @var array
     */
    private array $staff = [];


    /**
     * @var \Schoolbox\Resource\Folder\Model|null
     */
    private ?\Schoolbox\Resource\Folder\Model $folder;


    /* For resource booking purposes we need these method signatures
     * and corresponding properties
     */

    /**
     * @var bool $periodCancelled Whether the period is cancelled
     */
    protected bool $periodCancelled = false;


    /**
     * URL of the attendance link
     *
     * @var string
     */
    private string $attendanceLink = '';


    /**
     * How the attendance link should be opened
     * @var string
     */
    private string $attendanceLinkTarget = '_blank';


    /**
     * @var string|null
     */
    private ?string $attendanceUrl = null;


    /**
     * @return string[]
     */
    protected function getRenderClasses(): array
    {
        return [
            self::RENDER_FORMAT_JSON => Renderer\Javascript::class,
            self::RENDER_FORMAT_VCAL => Renderer\VObject::class,
            self::RENDER_FORMAT_CSV  => Renderer\Csv::class,
        ];
    }


    /**
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }


    /**
     * @param string|null $code
     * @return Event
     */
    public function setCode(?string $code): self
    {
        $this->code = $code;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getDay(): ?string
    {
        return $this->day;
    }


    /**
     * @param string|null $day
     * @return Event
     */
    public function setDay(?string $day): self
    {
        $this->day = $day;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getPeriod(): ?string
    {
        return $this->period;
    }


    /**
     * @param string|null $period
     * @return Event
     */
    public function setPeriod(?string $period): self
    {
        $this->period = $period;
        return $this;
    }


    /**
     * @return \Schoolbox\Resource\Folder\Model|null
     */
    public function getFolder(): ?\Schoolbox\Resource\Folder\Model
    {
        return $this->folder;
    }


    /**
     * @param \Schoolbox\Resource\Folder\Model|null $folder
     * @return Event
     */
    public function setFolder(?\Schoolbox\Resource\Folder\Model $folder): self
    {
        $this->folder = $folder;
        return $this;
    }


    /**
     * @return array
     */
    public function getStaff(): array
    {
        return $this->staff;
    }


    /**
     * @param array $staff
     * @return Event
     */
    public function setStaff(array $staff): self
    {
        $this->staff = $staff;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getAttendanceUrl(): ?string
    {
        return $this->attendanceUrl;
    }


    /**
     * @param string|null $attendanceUrl
     */
    public function setAttendanceUrl(?string $attendanceUrl): self
    {
        $this->attendanceUrl = $attendanceUrl;
        return $this;
    }


    /* For resource booking
     */

    /**
     * @return bool|null
     */
    public function getPeriodCancelled(): ?bool
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
     * @param string $attendanceLink
     */
    public function setAttendanceLink(string $attendanceLink): void
    {
        $this->attendanceLink = $attendanceLink;
    }


    /**
     * @return string|null
     */
    public function getAttendanceLink(): ?string
    {
        return $this->attendanceLink;
    }


    /**
     * @param string $attendanceLinkTarget
     */
    public function setAttendanceLinkTarget(string $attendanceLinkTarget): void
    {
        $this->attendanceLinkTarget = $attendanceLinkTarget;
    }

    /**
     * @return string|null
     */
    public function getAttendanceLinkTarget(): ?string
    {
        return $this->attendanceLinkTarget;
    }
}
