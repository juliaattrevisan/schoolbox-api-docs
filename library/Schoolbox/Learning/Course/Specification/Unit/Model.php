<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Specification\Unit;

use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\Specification\Model as CourseModel;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * A Unit Specification is a collection of Assessable objects. It exists on a Folder.
 *
 * It is intended to cover a single area of learning within a subject, and may be imported
 * onto another Folder as part of the action of importing a Course onto that folder.
 * This action creates an Unit Import for each Unit Specification that was chosen to be imported
 * as part of that process.
 *
 * (Historically, this was simply known as a Unit, and defined as a Folder which contained a
 *  Course Builder component. This class puts this concept into a concrete class.)
 *
 * @package Schoolbox\Learning\Course\Specification\Unit
 */
class Model implements AssessmentContext, ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use DeletedAtDate;

    /**
     * The Folder which this object belongs to.
     *
     * TODO review if this relation is required - this object can't exist without Folders
     *
     * @var FolderModel
     */
    protected $folder;

    /**
     * The Course Specification which this object belongs to.
     *
     * TODO review if this relation is required - this object can't exist without a Course Unit
     *
     * @var CourseModel
     */
    protected $course;

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
        $this->folder    = new FolderModel();
        $this->course    = new CourseModel();
        $this->createdAt = new \Alaress_Date_NullDate();
        $this->deletedAt = new \Alaress_Date_NullDate();
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
     * @return CourseModel
     */
    public function getCourse()
    {
        return $this->course;
    }

    /**
     * @param CourseModel $course
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
}
