<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Imported\Unit;

use Schoolbox\Collection;
use Schoolbox\Learning\Course\Imported\Model as CourseInstanceModel;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Learning\Course\Template\Unit\Model as UnitModel;

/**
 * A Unit Instance is a single import of a Unit onto a Folder. It belongs to a
 * Course Instance.
 *
 * On creation, a Unit Instance takes its Assessables from the Unit it was
 * created from - a teacher may then add, modify or remove individual
 * Assessable objects inside this object.
 *
 * TODO merge existing Unit Instance class (\Schoolbox\Assessment\Course\UnitInstance) into this
 *
 * @package Schoolbox\Learning\Course\Template
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
     * The Folder which this Unit Instance belongs to.
     *
     * TODO review if this relation is required - Units can't exist without Folders
     * Besides - this object is dependent on its Course Instance, which requires its Folder
     *
     * @var FolderModel
     */
    protected $folder;

    /**
     * The Course which this Unit Instance belongs to.
     *
     * TODO review if this relation is required - Unit Instances can't exist without their Course Instance
     *
     * @var CourseInstanceModel
     */
    protected $courseInstance;

    /**
     * The Unit which this Unit Instance was created from.
     *
     * @var UnitModel
     */
    protected $unit;

    /**
     * The Assessable objects which are a part of this Unit Instance.
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
        $this->unit           = new UnitModel();
        $this->folder         = new FolderModel();
        $this->courseInstance = new CourseInstanceModel();
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
     * @return UnitModel
     */
    public function getUnit()
    {
        return $this->unit;
    }

    /**
     * @param UnitModel $unit
     * @return static
     */
    public function setUnit($unit)
    {
        $this->unit = $unit;
        return $this;
    }

    /**
     * @return CourseInstanceModel
     */
    public function getCourseInstance()
    {
        return $this->courseInstance;
    }

    /**
     * @param CourseInstanceModel $courseInstance
     * @return static
     */
    public function setCourseInstance($courseInstance)
    {
        $this->courseInstance = $courseInstance;
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
