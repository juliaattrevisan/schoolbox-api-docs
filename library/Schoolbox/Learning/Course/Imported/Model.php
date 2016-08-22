<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Imported;

use Schoolbox\Collection;
use Schoolbox\Learning\Course\Imported\Unit\Model as UnitInstanceModel;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * A Course Instance is an import of a Course onto a Folder.
 *
 * A Course Instance may contain several Unit Instances.
 *
 * (Historically, a Course Instance was defined as a Unit Instance which had at least one child
 *  Folder which contained a Course Builder component. This class puts this
 *  concept into a concrete class.)
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
     * The Folder within which this Course Instance lives.
     *
     * TODO review whether this relation needs to exist
     *
     * @var FolderModel
     */
    protected $folder;

    /**
     * The Unit Instances that are a part of this Course.
     *
     * @var Collection|UnitInstanceModel[]
     */
    protected $units;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->folder = new FolderModel();
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
     * @return Collection|UnitInstanceModel[]
     */
    public function getUnits()
    {
        return $this->units;
    }

    /**
     * @param Collection|UnitInstanceModel[] $units
     * @return static
     */
    public function setUnits($units)
    {
        $this->units = $units;
        return $this;
    }
}
