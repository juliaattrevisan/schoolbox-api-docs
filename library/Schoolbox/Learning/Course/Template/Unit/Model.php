<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Template\Unit;

use Schoolbox\Collection;
use Schoolbox\Learning\Course\Template\Model as CourseModel;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * A Template Unit is an extension of a Folder.
 *
 * This object may contain several Assessables, and may be imported onto another Folder
 * as part of the action of importing a Course onto a folder.
 * This action creates an Imported Unit for each Template Unit that was chosen to be imported
 * as part of that process.
 *
 * (Historically, this was simply known as a Unit, and defined as a Folder which contained a
 *  Course Builder component. This class puts this concept into a concrete class.)
 *
 * @package Schoolbox\Learning\Course\Template\Unit
 */
class Model
{
    /**
     * Unique identifier.
     *
     * @var int
     */
    protected $id;

    /**
     * The Folder which this object belongs to.
     *
     * TODO review if this relation is required - Template Units can't exist without Folders
     *
     * @var FolderModel
     */
    protected $folder;

    /**
     * The Template Course which this object belongs to.
     *
     * TODO review if this relation is required - Template Units can't exist without a Course Unit
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
        $this->folder = new FolderModel();
        $this->course = new CourseModel();
    }

    /* Getters/Setters */

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
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
