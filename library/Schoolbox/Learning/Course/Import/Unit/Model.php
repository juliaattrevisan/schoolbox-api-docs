<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Import\Unit;

use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\Import\Model as CourseImportModel;
use Schoolbox\Learning\Course\Specification\Unit\Model as UnitSpecificationModel;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;

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
 * TODO merge existing Unit Instance class (\Schoolbox\Assessment\Course\UnitInstance) into this
 *
 * @package Schoolbox\Learning\Course\Import\Unit
 */
class Model implements AssessmentContext, ISoftDeletable
{
    use CreatedAtDate;
    use DeletedAtDate;

    /**
     * Unique identifier.
     *
     * @var int
     */
    protected $id;

    /**
     * The Course Import which this object belongs to.
     *
     * TODO review if this relation is required - this object can't exist without its Course Import
     *
     * @var CourseImportModel
     */
    protected $course;

    /**
     * The Unit Specification which this object was created from.
     *
     * @var UnitSpecificationModel
     */
    protected $specification;

    /**
     * The Assessable objects which are a part of this object.
     *
     * TODO add the Assessable interface to this definition, once it exists
     *
     * @var Collection
     */
    protected $assessables;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->specification  = new UnitSpecificationModel();
        $this->course         = new CourseImportModel();
        $this->createdAt      = new \Alaress_Date_NullDate();
        $this->deletedAt      = new \Alaress_Date_NullDate();
    }

    /* Getters/Setters */

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

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
     * This object's assessment context is its Course Import's assessment context.
     */
    public function getFolder()
    {
        return $this->getCourse()->getFolder();
    }
}
