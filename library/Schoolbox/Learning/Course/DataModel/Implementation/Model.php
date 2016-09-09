<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Implementation;

use Alaress_Date_NullDate;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecificationModel;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Data domain model for Course Implementations.
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
    protected $folder;

    /**
     * The Course Specification model that this model belongs to.
     *
     * @var CourseSpecificationModel
     */
    protected $specification;

    /**
     * The unit spec models for this model.
     *
     * @var Unit\Model[]|Collection
     */
    protected $units;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->createdAt = new Alaress_Date_NullDate();
        $this->updatedAt = new Alaress_Date_NullDate();
        $this->deletedAt = new Alaress_Date_NullDate();
        $this->units     = new Collection();
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
    public function setFolder(FolderModel $folder = null)
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
     * @return Unit\Model[]|Collection
     */
    public function getUnits()
    {
        return $this->units;
    }

    /**
     * @param Unit\Model[]|Collection $units
     * @return static
     */
    public function setUnits(Collection $units)
    {
        $this->units = $units;
        return $this;
    }
}
