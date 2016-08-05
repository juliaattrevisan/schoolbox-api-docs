<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Class Model
 *
 * @package Schoolbox\Assessment\Course
 */
class Model
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var FolderModel
     */
    protected $folder;

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
}
