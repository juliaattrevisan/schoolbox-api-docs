<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;

/**
 * Data domain model for Course Specifications.
 */
class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var FolderModel
     */
    private $folder;

    /**
     * The unit spec models for this model.
     *
     * @var Unit\Model[]|Collection
     */
    private $unitSpecifications;

    /**
     * @var CourseImplementation[]|Collection
     */
    private $courseImplementations;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->unitSpecifications    = new GenericCollection(UnitSpecification::class);
        $this->courseImplementations = new GenericCollection(CourseImplementation::class);
    }

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
    public function setFolder(FolderModel $folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return Unit\Model[]|Collection
     */
    public function getUnits()
    {
        return $this->unitSpecifications;
    }

    /**
     * @param Unit\Model[]|GenericCollection $unitSpecifications
     * @return static
     */
    public function setUnits(GenericCollection $unitSpecifications)
    {
        $this->unitSpecifications->assertSameItemClass($unitSpecifications);
        $this->unitSpecifications = $unitSpecifications;
        return $this;
    }

    /**
     * Gets name of folder on which this UnitSpec resides
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getFolder()->getName();
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
     */
    public function setCourseImplementations(GenericCollection $courseImplementations)
    {
        $this->courseImplementations->assertSameItemClass($courseImplementations);
        $this->courseImplementations = $courseImplementations;
        return $this;
    }
}
