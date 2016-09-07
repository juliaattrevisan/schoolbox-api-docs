<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course;

use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * An Course Implementation is an import of a Course Specification onto a Folder.
 *
 * This object will contain one or more Unit Implementations (whichever of the
 * Course Specification's Unit Specifications were selected as part of the import process).
 *
 * (Historically, this did not exist as a separate concept. Unit Implementations were
 *  related directly to the Folder the Course Specification was imported onto, and
 *  linked to a component.)
 *
 * TODO unit tests
 *
 * @package Schoolbox\Learning\Course\Implementation
 */
class Implementation implements AssessmentContext, ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * The Folder within which this object lives.
     *
     * TODO review whether this relation needs to exist
     *
     * @var Folder
     */
    protected $folder;

    /**
     * The Course Specification which this object was created from.
     *
     * @var CourseSpecification
     */
    protected $specification;

    /**
     * The Unit Implementations that are a part of this object.
     *
     * @var Collection|UnitImplementation[]
     */
    protected $units;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->units = new Collection();
        $this->createdAt = new \Alaress_Date_NullDate();
        $this->updatedAt = new \Alaress_Date_NullDate();
        $this->deletedAt = new \Alaress_Date_NullDate();
    }

    /* Getters/Setters */

    /**
     * @return Folder
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param Folder $folder
     * @return static
     */
    public function setFolder(Folder $folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return CourseSpecification
     */
    public function getSpecification()
    {
        return $this->specification;
    }

    /**
     * @param CourseSpecification $specification
     * @return static
     */
    public function setSpecification(CourseSpecification $specification)
    {
        $this->specification = $specification;
        return $this;
    }

    /**
     * @return Collection|UnitImplementation[]
     */
    public function getUnits()
    {
        return $this->units;
    }

    /**
     * @param Collection|UnitImplementation[] $units
     * @return static
     */
    public function setUnits(Collection $units)
    {
        $this->units = $units;
        return $this;
    }

    /**
     * Deep clone object properties.
     */
    public function __clone()
    {
        if (is_object($this->getCreatedAt())) {
            $this->setCreatedAt(clone $this->getCreatedAt());
        }
        if (is_object($this->getUpdatedAt())) {
            $this->setUpdatedAt(clone $this->getUpdatedAt());
        }
        if (is_object($this->getDeletedAt())) {
            $this->setDeletedAt(clone $this->getDeletedAt());
        }
        $this->setUnits($this->getUnits()->deepClone());
    }
}
