<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Alaress_Date_Format;

/**
 * Object representation of a curriculum from Atlas Rubicon.
 */
class Curriculum
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
     * @var string
     */
    private $subject;

    /**
     * @var string
     */
    private $grade;

    /**
     * @var string
     */
    private $school;

    /**
     * @var string
     */
    private $description;

    /**
     * @var \Alaress_Date_Format
     */
    private $lastUpdated;

    /**
     * @var string
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
     * @return string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return static
     */
    public function setTitle(?string $title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     * @return static
     */
    public function setSubject(?string $subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return string
     */
    public function getGrade(): ?string
    {
        return $this->grade;
    }

    /**
     * @param string $grade
     * @return static
     */
    public function setGrade(?string $grade)
    {
        $this->grade = $grade;
        return $this;
    }

    /**
     * @return string
     */
    public function getSchool(): ?string
    {
        return $this->school;
    }

    /**
     * @param string $school
     * @return static
     */
    public function setSchool(?string $school)
    {
        $this->school = $school;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return static
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getLastUpdated(): ?Alaress_Date_Format
    {
        return $this->lastUpdated;
    }

    /**
     * @param Alaress_Date_Format $lastUpdated
     * @return static
     */
    public function setLastUpdated(?Alaress_Date_Format $lastUpdated)
    {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    /**
     * @return string
     */
    public function getMapType(): ?string
    {
        return $this->mapType;
    }

    /**
     * @param string $mapType
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
}
