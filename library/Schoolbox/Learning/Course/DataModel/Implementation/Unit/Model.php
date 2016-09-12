<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Implementation\Unit;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementationModel;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecificationModel;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Data domain model for Unit Implementations.
 */
class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var CourseImplementationModel
     */
    protected $course;

    /**
     * @var UnitSpecificationModel
     */
    protected $specification;

    /**
     * @deprecated use $this->getCourse()->getFolder() instead
     *
     * @var FolderModel
     */
    protected $folder;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var Alaress_Date_Format
     */
    protected $openDate;

    /**
     * @var Alaress_Date_Format
     */
    protected $dueDate;

    /**
     * The assessables that are a part of this unit implementation.
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
        $this->openDate    = new Alaress_Date_NullDate();
        $this->dueDate     = new Alaress_Date_NullDate();
        $this->createdAt   = new Alaress_Date_NullDate();
        $this->updatedAt   = new Alaress_Date_NullDate();
        $this->deletedAt   = new Alaress_Date_NullDate();
        $this->assessables = new Collection();
    }

    /**
     * @return CourseImplementationModel
     */
    public function getCourse()
    {
        return $this->course;
    }

    /**
     * @param CourseImplementationModel $course
     * @return static
     */
    public function setCourse($course)
    {
        $this->course = $course;
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
    public function setAssessables($assessables)
    {
        $this->assessables = $assessables;
        return $this;
    }
}
