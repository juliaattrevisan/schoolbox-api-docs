<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Import\Unit;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\Import\Model as CourseImportModel;
use Schoolbox\Learning\Course\Specification\Unit\Model as UnitSpecificationModel;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * An Unit Import is a single import of a Unit Specification onto a Folder. It belongs to an
 * Course Import.
 *
 * On creation, this object makes a copy of the Assessables in the Unit Specification it was
 * created from, and adds them to itself - a teacher may then add, modify or remove individual
 * Assessable objects inside this object.
 *
 * (Historically, this was known as a Unit Instance. It did not belong to an
 *  Course Import - instead, it belonged directly to the Folder it was imported
 *  onto, and was also linked to a component on that Folder.)
 *
 * @package Schoolbox\Learning\Course\Import\Unit
 */
class Model implements AssessmentContext, ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use DeletedAtDate;

    /**
     * The Course Import which this object belongs to.
     *
     * Replaces the previous Unit Instance class' component field.
     *
     * TODO review if this relation is required - this object can't exist without its Course Import
     *
     * @var CourseImportModel
     */
    protected $course;

    /**
     * The Unit Specification which this object was created from.
     *
     * Replaces the previous Unit Instance class' unit_category field.
     *
     * @var UnitSpecificationModel
     */
    protected $specification;

    /**
     * The Assessable objects which are a part of this object.
     *
     * Replaces the previous Unit Instance class' tasks collection.
     *
     * TODO add the Assessable interface to this definition, once it exists
     *
     * @var Collection
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
        $this->specification  = new UnitSpecificationModel();
        $this->course         = new CourseImportModel();
        $this->openDate       = new \Alaress_Date_NullDate();
        $this->dueDate        = new \Alaress_Date_NullDate();
        $this->createdAt      = new \Alaress_Date_NullDate();
        $this->deletedAt      = new \Alaress_Date_NullDate();
    }

    /* Getters/Setters */

    /**
     * @return UnitSpecificationModel
     */
    public function getSpecification()
    {
        return $this->specification;
    }

    /**
     * @param UnitSpecificationModel $specification
     * @return static
     */
    public function setSpecification($specification)
    {
        $this->specification = $specification;
        return $this;
    }

    /**
     * @return CourseImportModel
     */
    public function getCourse()
    {
        return $this->course;
    }

    /**
     * @param CourseImportModel $course
     * @return static
     */
    public function setCourse($course)
    {
        $this->course = $course;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getAssessables()
    {
        return $this->assessables;
    }

    /**
     * @param Collection $assessables
     * @return static
     */
    public function setAssessables($assessables)
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
    public function setOpenDate($openDate)
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
    public function setDueDate($dueDate)
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    /**
     * This object's assessment context is its Course Import's assessment context.
     */
    public function getFolder()
    {
        return $this->getCourse()->getFolder();
    }
}
