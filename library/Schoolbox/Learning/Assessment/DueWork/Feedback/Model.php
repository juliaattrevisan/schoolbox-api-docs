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

use \Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\AbstractFeedback;

class Model extends AbstractFeedback
{

    /**
     * All the Files that are part of the Feedback made by Teacher
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