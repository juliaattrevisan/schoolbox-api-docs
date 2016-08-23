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
 * An Imported Course is an import of a Template Course onto a Folder.
 *
 * This object will contain one or more Imported Units (whichever of the
 * Template Course's Template Units were selected as part of the import process).
 *
 * (Historically, this did not exist as a separate concept. Imported Units were
 *  related directly to the Folder the Template Course was imported onto, and
 *  linked to a component.)
 *
 * @package Schoolbox\Learning\Course\Imported
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
     * The Folder within which this object lives.
     *
     * TODO review whether this relation needs to exist
     *
     * @var FolderModel
     */
    protected $folder;

    /**
     * The Imported Units that are a part of this object.
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
