<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\DataModel\AbstractCourse;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;

/**
 * A Course Specification is a collection of Unit Specifications. It exists on a Folder.
 *
 * It is intended to represent an entire subject's syllabus, and may be imported onto another Folder.
 * This action will create a corresponding Course Implementation for this Course Specification,
 * with whatever Unit Specifications were selected to be imported.
 *
 * (Historically, this was simply known as a Course, and was defined as a Folder
 *  which had at least one child Folder which contained a Course Builder component.
 *  This class puts this concept into a concrete class.)
 */
class Model extends AbstractCourse
{
    /**
     * The unit spec models for this model.
     *
     * @var Unit\Model[]|GenericCollection
     */
    private $unitSpecifications;

    /**
     * @var CourseImplementation[]|GenericCollection
     */
    private $courseImplementations;

    private null|string $subjectCode;


    /**
     * Constructor
     *
     * @throws \UnexpectedValueException
     */
    public function __construct()
    {
        $this->unitSpecifications    = new GenericCollection(UnitSpecification::class);
        $this->courseImplementations = new GenericCollection(CourseImplementation::class);
    }

    /**
     * Gets name of folder on which this UnitSpec resides
     *
     * @return string
     */
    public function getTitle()
    {
        if ($this->getFolder() != null) {
            return $this->getFolder()->getName();
        }
        return '';
    }

    /* Getters / Setters
     */

    /**
     * @return Unit\Model[]|GenericCollection
     */
    public function getUnits(): GenericCollection
    {
        return $this->unitSpecifications;
    }


    /**
     * @param Unit\Model[]|GenericCollection $unitSpecifications
     * @return static
     * @throws \DomainException
     */
    public function setUnits(GenericCollection $unitSpecifications): self
    {
        $this->unitSpecifications->replaceWith($unitSpecifications);
        return $this;
    }

    /**
     * @return CourseImplementation[]|Collection
     */
    public function getCourseImplementations()
    {
        return $this->courseImplementations;
    }


    /**
     * @param CourseImplementation[]|GenericCollection $courseImplementations
     * @return static
     * @throws \DomainException
     */
    public function setCourseImplementations(GenericCollection $courseImplementations)
    {
        $this->courseImplementations->replaceWith($courseImplementations);
        return $this;
    }

    /**
     * @param string|null $subjectCode
     * @return $this
     */
    public function setSubjectCode(?string $subjectCode)
    {
        $this->subjectCode = $subjectCode;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSubjectCode()
    {
        return $this->subjectCode;
    }
}
