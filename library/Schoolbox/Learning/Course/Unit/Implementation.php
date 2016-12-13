<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Unit;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

/**
 * An Unit Implementation is a single import of a Unit Specification onto a Folder. It belongs to an
 * Course Implementation.
 *
 * On creation, this object makes a copy of the Assessables in the Unit Specification it was
 * created from, and adds them to itself - a teacher may then add, modify or remove individual
 * Assessable objects inside this object.
 *
 * (Historically, this was known as a Unit Instance. It did not belong to a
 *  Course Implementation - instead, it belonged directly to the Folder it was imported
 *  onto, and was also linked to a component on that Folder.)
 *
 * @package Schoolbox\Learning\Course\Implementation\Unit
 */
class Implementation implements AssessmentContext, ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * The Course Implementation which this object belongs to.
     *
     * Replaces the previous Unit Instance class' component field.
     *
     * TODO review if this relation is required - this object can't exist without its Course Implementation
     *
     * @var CourseImplementation
     */
    protected $course;

    /**
     * The Unit Specification which this object was created from.
     *
     * Replaces the previous Unit Instance class' unit_category field.
     *
     * @var UnitSpecification
     */
    protected $specification;

    /**
     * The Assessable objects which are a part of this object.
     *
     * Replaces the previous Unit Instance class' tasks collection.
     *
     * @var Collection|IAssessable[]
     */
    protected $assessables;

    /**
     * The title of this object.
     *
     * Usually this will come from this object's specification (usually the
     * specification's folder's name), but it exists as a separate field in
     * the database.
     *
     * TODO review whether this field is required
     *
     * @var string
     */
    protected $title;

    /**
     * The date on which this object opens.
     *
     * Assessables within this object may have their own open dates.
     *
     * @var Alaress_Date_Format
     */
    protected $openDate;

    /**
     * The date on which this object is due.
     *
     * Assessables within this object may have their own due dates.
     *
     * @var Alaress_Date_Format
     */
    protected $dueDate;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->openDate = new \Alaress_Date_NullDate();
        $this->dueDate = new \Alaress_Date_NullDate();
        $this->createdAt = new \Alaress_Date_NullDate();
        $this->updatedAt = new \Alaress_Date_NullDate();
        $this->deletedAt = new \Alaress_Date_NullDate();
        $this->assessables = new Collection();
    }

    /**
     * Populates this object's list of assessables from those from a Unit Specification.
     *
     * This method doesn't clear this object's assessables, or set this object's
     * name or spec - it simply clones the spec's assessables into this object.
     *
     * @deprecated use \Schoolbox\Learning\Course\Unit\ImplementationFactory::buildFromSpecification instead
     *
     * @param UnitSpecification $unitSpec
     * @return static
     */
    public function populateAssessablesFromSpecification(UnitSpecification $unitSpec)
    {
        foreach ($unitSpec->getAssessables() as $assessable) {
            // Maybe each assessable has a clone method?
            $newAssessable = clone $assessable;
            $newAssessable->setId(null);
            $this->getAssessables()->add($newAssessable);
        }
        return $this;
    }

    /* Getters/Setters */

    /**
     * @return UnitSpecification
     */
    public function getSpecification()
    {
        return $this->specification;
    }

    /**
     * @param UnitSpecification $specification
     * @return static
     */
    public function setSpecification(UnitSpecification $specification)
    {
        $this->specification = $specification;
        return $this;
    }

    /**
     * @return Collection|IAssessable[]
     */
    public function getAssessables()
    {
        return $this->assessables;
    }

    /**
     * @param Collection|IAssessable[] $assessables
     * @return static
     */
    public function setAssessables(Collection $assessables)
    {
        $this->assessables = $assessables;
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
     * @param string $title
     * @return static
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getOpenDate()
    {
        return $this->openDate;
    }

    /**
     * @param Alaress_Date_Format $openDate
     * @return static
     */
    public function setOpenDate(Alaress_Date_Format $openDate)
    {
        $this->openDate = $openDate;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDueDate()
    {
        return $this->dueDate;
    }

    /**
     * @param Alaress_Date_Format $dueDate
     * @return static
     */
    public function setDueDate(Alaress_Date_Format $dueDate)
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    /**
     * This object's assessment context is its Course Implementation's assessment context.
     */
    public function getFolder()
    {
        if ($this->getCourse() instanceof CourseImplementation) {
            return $this->getCourse()->getFolder();
        }
        return null;
    }

    /**
     * @return CourseImplementation
     */
    public function getCourse()
    {
        return $this->course;
    }

    /**
     * @param CourseImplementation $course
     * @return static
     */
    public function setCourse(CourseImplementation $course)
    {
        $this->course = $course;
        return $this;
    }

    /**
     * @return $this
     */
    public function getBaseContext()
    {
        return $this;
    }

    /**
     * This unit is open if the current date is after the open date, and before
     * the due date.
     *
     * @return bool
     */
    public function isOpen() {
        return !($this->isBeforeOpenDate() || $this->isAfterDueDate());
    }

    /**
     * Is this unit open for submissions?
     *
     * @return bool
     */
    public function isBeforeOpenDate() {
        $date = new Alaress_Date_Format(new \DateTime());
        return $date->compareDateOnly($this->getOpenDate()) < 0;
    }

    /**
     * Is this unit open for submissions?
     *
     * @return bool
     */
    public function isAfterDueDate() {
        $date = new Alaress_Date_Format(new \DateTime());
        return $date->compareDateOnly($this->getDueDate()) > 0;
    }

    /**
     * Retrieves a time relative to whenever the course unit opens.
     *
     * It opens on 12am of the open date.
     *
     * @return Alaress_Date_Format
     */
    public function getRelativeOpenDate() {
        $date = clone $this->getOpenDate()->getDateTimeObject();
        $date->modify('00:00:00');
        return new Alaress_Date_Format($date);
    }

    /**
     * Retrieves a time relative to whenever the course unit closes.
     *
     * It closes on the last second of the due date.
     *
     * @return Alaress_Date_Format
     */
    public function getRelativeDueDate() {
        $date = clone $this->getDueDate()->getDateTimeObject();
        $date->modify('23:59:59');
        return new Alaress_Date_Format($date);
    }
    /**
     * Deep clone object properties.
     */
    public function __clone()
    {
        if (is_object($this->getOpenDate())) {
            $this->setOpenDate(clone $this->getOpenDate());
        }
        if (is_object($this->getDueDate())) {
            $this->setDueDate(clone $this->getDueDate());
        }
        if (is_object($this->getCreatedAt())) {
            $this->setCreatedAt(clone $this->getCreatedAt());
        }
        if (is_object($this->getUpdatedAt())) {
            $this->setUpdatedAt(clone $this->getUpdatedAt());
        }
        if (is_object($this->getDeletedAt())) {
            $this->setDeletedAt(clone $this->getDeletedAt());
        }
        $this->setAssessables($this->getAssessables()->deepClone());
    }
}
