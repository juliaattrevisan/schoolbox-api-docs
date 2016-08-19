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

    /**
     * The person who owns this Submission
     *
     * Teachers can submit on behalf of a Student - the Student
     * is the User instance here
     *
     * @var User
     */
    protected $submittedOnBehalfOf;

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
     * When the Submission was submitted
     * An alias to createdAt
     *
     * @return \Alaress_Date_Format
     */
    public function getSubmittedAt()
    {
        return $this->getCreatedAt();
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

    /**
     * @return User
     */
    public function getSubmittedOnBehalfOf()
    {
        return $this->submittedOnBehalfOf;
    }

    /**
     * @param User $submittedOnBehalfOf
     * @return $this
     */
    public function setSubmittedOnBehalfOf($submittedOnBehalfOf)
    {
        $this->submittedOnBehalfOf = $submittedOnBehalfOf;
        return $this;
    }
}