<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Import;

use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\Specification\Model as CourseSpecificationModel;
use Schoolbox\Learning\Course\Import\Unit\Model as UnitImportModel;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * An Course Import is an import of a Course Specification onto a Folder.
 *
 * This object will contain one or more Unit Imports (whichever of the
 * Course Specification's Unit Specifications were selected as part of the import process).
 *
 * (Historically, this did not exist as a separate concept. Unit Imports were
 *  related directly to the Folder the Course Specification was imported onto, and
 *  linked to a component.)
 *
 * @package Schoolbox\Learning\Course\Import
 */
class Model implements AssessmentContext, ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use DeletedAtDate;

    /**
     * The Folder within which this object lives.
     *
     * TODO review whether this relation needs to exist
     *
     * @var FolderModel
     */
    protected $folder;

    /**
     * The Course Specification which this object was created from.
     *
     * @var CourseSpecificationModel
     */
    protected $specification;

    /**
     * The Unit Imports that are a part of this object.
     *
     * @var Collection|UnitImportModel[]
     */
    protected $units;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->folder        = new FolderModel();
        $this->units         = new Collection();
        $this->createdAt     = new \Alaress_Date_NullDate();
        $this->deletedAt     = new \Alaress_Date_NullDate();
        $this->specification = new CourseSpecificationModel();
    }

    /* Getters/Setters */

    /**
     * @return FolderModel
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param FolderModel $folder
     * @return static
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return CourseSpecificationModel
     */
    public function getSpecification()
    {
        return $this->specification;
    }

    /**
     * @param CourseSpecificationModel $specification
     * @return static
     */
    public function setSpecification($specification)
    {
        $this->specification = $specification;
        return $this;
    }

    /**
     * @return Collection|UnitImportModel[]
     */
    public function getUnits()
    {
        return $this->units;
    }

    /**
     * @param Collection|UnitImportModel[] $units
     * @return static
     */
    public function setUnits($units)
    {
        $this->units = $units;
        return $this;
    }
}
