<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Alaress_Date_Format;
use \Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\AbstractFeedback;

class Model extends AbstractFeedback
{

    /**
     * All the Files that are part of the Feedback made by Teacher
     *
     * @var Collection|File\Model[]
     */
    protected $allFiles;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        $this->setAllFiles(new Collection());
    }

    /* Getters/Setters
     */

    /**
     * @return Collection|File\Model[]
     */
    public function getAllFiles()
    {
        return $this->allFiles;
    }

    /**
     * @param Collection|File\Model[] $allFiles
     * @return self
     */
    public function setAllFiles($allFiles)
    {
        $this->allFiles = $allFiles;
        return $this;
    }


    /**
     * @param Alaress_Date_Format $updatedAt
     * @return $this
     */
    public function setUpdatedAt(Alaress_Date_Format $updatedAt = null)
    {
        if (empty($this->getCreatedAt())) {
            $this->setCreatedAt($updatedAt);
        }
        return $this;
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getUpdatedAt()
    {
        return $this->getCreatedAt();
    }

}
