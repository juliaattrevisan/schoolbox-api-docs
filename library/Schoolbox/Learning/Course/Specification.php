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
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Folder\Model as Folder;

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
 *
 * TODO unit tests
 *
 * @package Schoolbox\Learning\Course
 */
class Specification implements AssessmentContext, ISoftDeletable
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
     * The Unit Specifications that are a part of this object.
     *
     * @var Collection|Unit\Specification[]
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
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return Collection|Unit\Specification[]
     */
    public function getUnits()
    {
        return $this->units;
    }

    /**
     * @param Collection|Unit\Specification[] $units
     * @return static
     */
    public function setUnits($units)
    {
        $this->units = $units;
        return $this;
    }

    /**
     * Deep clone object properties.
     */
    public function __clone()
    {
        $this
            ->setCreatedAt(clone $this->getCreatedAt())
            ->setUpdatedAt(clone $this->getUpdatedAt())
            ->setDeletedAt(clone $this->getDeletedAt())
            ->setUnits(clone $this->getUnits());
    }
}
