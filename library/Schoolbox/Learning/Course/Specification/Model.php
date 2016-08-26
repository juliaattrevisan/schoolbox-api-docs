<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Specification;

use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * A Course Specification is an extension of a Folder.
 *
 * This object may contain several Unit Specifications, and may be imported onto another Folder.
 * This action will create a corresponding Imported Course for this Course Specification,
 * with whatever Unit Specifications were selected to be imported.
 *
 * (Historically, this was simply known as a Course, and was defined as a Folder
 *  which had at least one child Folder which contained a Course Builder component.
 *  This class puts this concept into a concrete class.)
 *
 * @package Schoolbox\Learning\Course\Specification
 */
class Model implements AssessmentContext, ISoftDeletable
{
    use CreatedAtDate;
    use DeletedAtDate;

    /**
     * Unique identifier.
     *
     * @var int
     */
    protected $id;

    /**
     * The Folder within which this object lives.
     *
     * TODO review whether this relation needs to exist
     *
     * @var FolderModel
     */
    protected $folder;

    /**
     * The Unit Specifications that are a part of this object.
     *
     * @var Collection|Unit\Model[]
     */
    protected $units;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->folder    = new FolderModel();
        $this->units     = new Collection();
        $this->createdAt = new \Alaress_Date_NullDate();
        $this->deletedAt = new \Alaress_Date_NullDate();
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
     * @return Collection|Unit\Model[]
     */
    public function getUnits()
    {
        return $this->units;
    }

    /**
     * @param Collection|Unit\Model[] $units
     * @return static
     */
    public function setUnits($units)
    {
        $this->units = $units;
        return $this;
    }
}
