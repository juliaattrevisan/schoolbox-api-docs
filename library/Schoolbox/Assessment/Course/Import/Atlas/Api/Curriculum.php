<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Alaress_Date_Format;
use JsonSerializable;

/**
 * Object representation of a curriculum from Atlas Rubicon.
 */
class Curriculum implements JsonSerializable
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var null|string
     */
    private $title;

    /**
     * @var null|string
     */
    private $subject;

    /**
     * @var null|string
     */
    private $grade;

    /**
     * @var null|string
     */
    private $school;

    /**
     * @var null|string
     */
    private $description;

    /**
     * @var null|\Alaress_Date_Format
     */
    private $lastUpdated;

    /**
     * @var null|string
     */
    private $mapType;

    /**
     * @var Teacher[]
     */
    private $teachers = [];

    /**
     * @var Unit[]
     */
    private $units = [];

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId(int $id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param null|string $title
     * @return static
     */
    public function setTitle(?string $title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    /**
     * @param null|string $subject
     * @return static
     */
    public function setSubject(?string $subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getGrade(): ?string
    {
        return $this->grade;
    }

    /**
     * @param null|string $grade
     * @return static
     */
    public function setGrade(?string $grade)
    {
        $this->grade = $grade;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getSchool(): ?string
    {
        return $this->school;
    }

    /**
     * @param null|string $school
     * @return static
     */
    public function setSchool(?string $school)
    {
        $this->school = $school;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param null|string $description
     * @return static
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return null|Alaress_Date_Format
     */
    public function getLastUpdated(): ?Alaress_Date_Format
    {
        return $this->lastUpdated;
    }

    /**
     * @param null|Alaress_Date_Format $lastUpdated
     * @return static
     */
    public function setLastUpdated(?Alaress_Date_Format $lastUpdated)
    {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getMapType(): ?string
    {
        return $this->mapType;
    }

    /**
     * @param null|string $mapType
     * @return static
     */
    public function setMapType(?string $mapType)
    {
        $this->mapType = $mapType;
        return $this;
    }

    /**
     * @return Teacher[]
     */
    public function getTeachers(): array
    {
        return $this->teachers;
    }

    /**
     * @param Teacher[] $teachers
     * @return static
     */
    public function setTeachers(array $teachers)
    {
        $this->teachers = $teachers;
        return $this;
    }

    /**
     * @return Unit[]
     */
    public function getUnits(): array
    {
        return $this->units;
    }

    /**
     * @param Unit[] $units
     * @return static
     */
    public function setUnits(array $units)
    {
        $this->units = $units;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'school'      => $this->school,
            'grade'       => $this->grade,
            'subject'     => $this->subject,
            'description' => $this->description,
            'mapType'     => $this->mapType,
            'lastUpdated' => $this->lastUpdated,
            'teachers'    => $this->teachers,
            'units'       => $this->units,
        ];
    }
}
