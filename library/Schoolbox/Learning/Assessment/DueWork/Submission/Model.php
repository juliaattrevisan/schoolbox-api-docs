<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\DueWork\Submission;

use \Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\AbstractResponse;

class Model extends AbstractResponse
{
    /**
     * All the Files that are part of the Submission made by Student
     *
     * @var Collection
     */
    protected $allFiles;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        /* I know Dr hates hook methods, so do this instead
         */
        parent::__construct();

        $this->setAllFiles(new Collection());
    }

    /* Getters/Setters
     */

    /**
     * @return Collection
     */
    public function getAllFiles()
    {
        return $this->allFiles;
    }

    /**
     * @param Collection $allFiles
     * @return self
     */
    public function setAllFiles($allFiles)
    {
        $this->allFiles = $allFiles;
        return $this;
    }
}