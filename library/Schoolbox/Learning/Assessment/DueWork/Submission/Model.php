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
use Schoolbox\Resource\Storage\IStorage;
use \Schoolbox\User;

use \Schoolbox\Learning\Assessment\AbstractResponse;

class Model extends AbstractResponse
{
    /**
     * All the Files that are part of the Submission made by Student
     *
     * @var Collection
     */
    protected $allFiles;

    protected $submittedAt;

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

    /**
     * Add the contents of the wizzywig to this Response
     * (add it as a file)
     *
     * @param $body
     * @return $this
     */
    public function attachOnlineSubmissionContent($body)
    {
        $storage = \Schoolbox\Resource\Storage\Mapper::getInstance()->acceptStream('online-submission.html', $body, false);

        $this->getAllFiles()->add($storage);

        return $this;
    }

    /* Getters/Setters
     */

    /**
     * @return Collection|IStorage[]
     */
    public function getAllFiles()
    {
        return $this->allFiles;
    }

    /**
     * @param Collection|IStorage[] $allFiles
     * @return self
     */
    public function setAllFiles($allFiles)
    {
        $this->allFiles = $allFiles;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSubmittedAt()
    {
        return $this->submittedAt;
    }

    /**
     * @param mixed $submittedAt
     * @return $this
     */
    public function setSubmittedAt($submittedAt)
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }
}