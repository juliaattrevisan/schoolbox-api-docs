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
}
