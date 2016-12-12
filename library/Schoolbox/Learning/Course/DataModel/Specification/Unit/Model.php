<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification\Unit;

use Alaress_Date_NullDate;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\IAssessmentContextDataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecificationModel;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;

/**
 * Data domain model for Unit Specifications.
 */
class Model implements ISoftDeletable, AssessmentContext, IAssessmentContextDataModel
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var CourseSpecificationModel
     */
    private $course;

    /**
     * @var FolderModel
     */
    private $folder;

    /**
     * The assessables that are a part of this unit spec.
     *
     * @var IAssessable[]|Collection
     */
    private $assessables;


    /**
     * @var UnitImplementation[]|Collection
     */
    private $unitImplementations;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->assessables = new Collection();
    }

    /**
     * @return CourseSpecificationModel
     */
    public function getCourse()
    {
        return $this->course;
    }

    /**
     * @param CourseSpecificationModel $course
     * @return static
     */
    public function setCourse(CourseSpecificationModel $course)
    {
        $this->course = $course;
        return $this;
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
     * @return Collection|UnitImplementation[]
     */
    public function getUnitImplementations()
    {
        return $this->unitImplementations;
    }

    /**
     * @param Collection|UnitImplementation[] $unitImplementations
     * @return static
     */
    public function setUnitImplementations($unitImplementations)
    {
        $this->unitImplementations = $unitImplementations;
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
     * Returns title of folder, for AssessmentContext Interface
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getFolder()->getTitle();
    }
}
