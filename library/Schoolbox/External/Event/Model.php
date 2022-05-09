<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event;

use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;


    /**
     * These consts correspond to the Classification enum
     */
    public const CLASSIFICATION_EVENT = 'event';
    public const CLASSIFICATION_TIMETABLE = 'timetable';


    /**
     * Initial condition for all-day flag
     * All events are timed unless specified otherwise
     */
    public const NOT_ALL_DAY = false;
    public const ALL_DAY = true;


    /**
     * @var string|null
     */
    private ?string $title = null;


    /**
     * @var string|null
     */
    private ?string $detail = null;


    /**
     * @var string|null
     */
    private ?string $location = null;


    /**
     * @var string|null
     */
    private ?string $code = null;


    /**
     * @var string|null
     */
    private ?string $day = null;


    /**
     * @var string|null
     */
    private ?string $period = null;


    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $startAt;


    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $finishAt;


    /**
     * @var bool|null
     */
    private ?bool $allday = null;


    /**
     * @var string|null
     */
    private ?string $type = null;


    /**
     * @var string|null
     */
    private ?string $classification = null;


    /**
     * @var string|null
     */
    private ?string $guid = null;


    /**
     * @var string|null
     */
    private ?string $source = null;


    /**
     * @var \Schoolbox\Resource\Folder\Model|null
     */
    private ?\Schoolbox\Resource\Folder\Model $folder = null;


    /**
     * @var array
     */
    private array $staff = [];


    /**
     * Used in conjunction with findWithCheapStaff() to trickle users
     *
     * @param int    $userId
     * @param string $name
     * @return void
     */
    public function addStaff(int $userId, string $name): void
    {
        $this->staff[$userId] = $name;
    }


    /* Getters/Setters
     */

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }


    /**
     * @param string|null $title
     * @return Model
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getDetail(): ?string
    {
        return $this->detail;
    }


    /**
     * @param string|null $detail
     * @return Model
     */
    public function setDetail(?string $detail): self
    {
        $this->detail = $detail;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }


    /**
     * @param string|null $location
     * @return Model
     */
    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
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
     * @return Model
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
     * @return Model
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
     * @return Model
     */
    public function setPeriod(?string $period): self
    {
        $this->period = $period;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getStartAt(): ?\Alaress_Date_Format
    {
        return $this->startAt;
    }


    /**
     * @param \Alaress_Date_Format|null $startAt
     * @return Model
     */
    public function setStartAt(?\Alaress_Date_Format $startAt): self
    {
        $this->startAt = $startAt;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getFinishAt(): ?\Alaress_Date_Format
    {
        return $this->finishAt;
    }


    /**
     * @return bool|null
     */
    public function getAllday(): ?bool
    {
        return $this->allday;
    }


    /**
     * @param bool|null $allday
     * @return Model
     */
    public function setAllday(?bool $allday): self
    {
        $this->allday = $allday;
        return $this;
    }


    /**
     * @param \Alaress_Date_Format|null $finishAt
     * @return Model
     */
    public function setFinishAt(?\Alaress_Date_Format $finishAt): self
    {
        $this->finishAt = $finishAt;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }


    /**
     * @param string|null $type
     * @return Model
     */
    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getClassification(): ?string
    {
        return $this->classification;
    }


    /**
     * @param string|null $classification
     * @return Model
     */
    public function setClassification(?string $classification): self
    {
        $this->classification = $classification;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getGuid(): ?string
    {
        return $this->guid;
    }


    /**
     * @param string|null $guid
     * @return Model
     */
    public function setGuid(?string $guid): self
    {
        $this->guid = $guid;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getSource(): ?string
    {
        return $this->source;
    }


    /**
     * @param string|null $source
     * @return Model
     */
    public function setSource(?string $source): self
    {
        $this->source = $source;
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
     * @return $this
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
     * @return $this
     */
    public function setStaff(array $staff): self
    {
        $this->staff = $staff;
        return $this;
    }
}
