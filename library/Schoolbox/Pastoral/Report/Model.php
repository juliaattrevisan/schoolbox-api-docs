<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Report;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var bool $dashboard */
    protected $dashboard;
    
    /** @var string $name */
    protected $name;

    /** @var string $description */
    protected $description;

    /** @var Collection $years */
    protected $years;

    /** @var bool $involved */
    protected $involved;

    /** @var Alaress_Date_Format $afterDate */
    protected $afterDate;

    /** @var Alaress_Date_Format $beforeDate */
    protected $beforeDate;

    /** @var User $owner */
    protected $owner;

    // These are the filters stored as CSV strings

    /** @var string $typesCsv */
    protected $typesCsv;

    /** @var string $authorsCsv */
    protected $authorsCsv;

    /** @var string $studentsCsv */
    protected $studentsCsv;

    /** @var string $tagsCsv */
    protected $tagsCsv;

    /** @var string $orderCsv */
    protected $orderCsv;

    /** @var string $yearsCsv */
    protected $yearsCsv;

    // These are the collections that get hydrated from the CSV values above

    /** @var Collection $order */
    protected $order;

    /** @var Collection $tags */
    protected $tags;

    /** @var Collection $students */
    protected $students;

    /** @var Collection $authors */
    protected $authors;

    /** @var Collection $types */
    protected $types;

    /** @var PastoralSeverity $severity */
    protected $severity;

    /** @var Collection $groups */
    protected $groups;

    /**
     * Model constructor
     */
    public function __construct()
    {
        $this->students = new Collection();
        $this->authors = new Collection();
        $this->order = new Collection();
        $this->tags = new Collection();
        
        // This is a collection of groups that have access to this report
        
        $this->groups = new Collection();
    }

    /**
     * @param PastoralSeverity $value
     * @return $this
     */
    public function setSeverity($value) {
        $this->severity = $value;
        return $this;
    }

    /**
     * @return PastoralSeverity
     */
    public function getSeverity() {
        return $this->severity;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setTypesCsv($value) {
        $this->typesCsv = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getTypesCsv() {
        return $this->typesCsv;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setAuthorsCsv($value) {
        $this->authorsCsv = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getAuthorsCsv() {
        return $this->authorsCsv;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setStudentsCsv($value) {
        $this->studentsCsv = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getStudentsCsv() {
        return $this->studentsCsv;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setTagsCsv($value) {
        $this->tagsCsv = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getTagsCsv() {
        return $this->tagsCsv;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setOrderCsv($value) {
        $this->orderCsv = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getOrderCsv() {
        return $this->orderCsv;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setYearsCsv($value) {
        $this->yearsCsv = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getYearsCsv() {
        return $this->yearsCsv;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $description
     * @return self
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param User $owner
     * @return self
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param Alaress_Date_Format $beforeDate
     * @return self
     */
    public function setBeforeDate($beforeDate)
    {
        $this->beforeDate = $beforeDate;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getBeforeDate()
    {
        return $this->beforeDate;
    }

    /**
     * @param Alaress_Date_Format $afterDate
     * @return self
     */
    public function setAfterDate($afterDate)
    {
        $this->afterDate = $afterDate;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getAfterDate()
    {
        return $this->afterDate;
    }

    /**
     * @param Collection $types
     * @return self
     */
    public function setTypes($types)
    {
        $this->types = $types;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @param Collection $authors
     * @return self
     */
    public function setAuthors($authors)
    {
        $this->authors = $authors;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getAuthors()
    {
        return $this->authors;
    }

    /**
     * @param Collection $students
     * @return self
     */
    public function setStudents($students)
    {
        $this->students = $students;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getStudents()
    {
        return $this->students;
    }

    /**
     * @param Collection $tags
     * @return self
     */
    public function setTags($tags)
    {
        $this->tags = $tags;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param Collection $order
     * @return self
     */
    public function setOrder($order)
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param bool $involved
     * @return self
     */
    public function setInvolved($involved)
    {
        $this->involved = $involved;
        return $this;
    }

    /**
     * @return bool
     */
    public function getInvolved()
    {
        return $this->involved;
    }

    /**
     * @param Collection $years
     * @return self
     */
    public function setYears($years)
    {
        $this->years = $years;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getYears()
    {
        return $this->years;
    }

    /**
     * @param Collection $groups
     * @return self
     */
    public function setGroups($groups)
    {
        $this->groups = $groups;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getGroups()
    {
        return $this->groups;
    }

    /**
     * @param bool $dashboard
     * @return self
     */
    public function setDashboard($dashboard)
    {
        $this->dashboard = $dashboard;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDashboard()
    {
        return $this->dashboard;
    }    
}