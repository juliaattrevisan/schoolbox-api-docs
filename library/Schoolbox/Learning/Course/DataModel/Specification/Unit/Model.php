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
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecificationModel;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Data domain model for Unit Specifications.
 */
class Model implements ISoftDeletable, IAssessmentContextDataModel
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var CourseSpecificationModel
     */
    protected $course;

    /**
     * @var FolderModel
     */
    protected $folder;

    /**
     * The assessables that are a part of this unit spec.
     *
     * TODO figure out whether they need to be translated or not
     *
     * @var IAssessable[]|Collection
     */
    protected $assessables;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->createdAt   = new Alaress_Date_NullDate();
        $this->updatedAt   = new Alaress_Date_NullDate();
        $this->deletedAt   = new Alaress_Date_NullDate();
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
}
