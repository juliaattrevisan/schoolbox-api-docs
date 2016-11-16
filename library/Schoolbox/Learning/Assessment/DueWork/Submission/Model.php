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

    /**
     * The datetime at which the Student actually made the submission
     * This is not the same as the createdAt or updatedAt datetimes
     *
     * @var \Alaress_Date_Format
     */
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

        $this->getAllFiles()->add(
            (new File\Model())
                ->setStorage($storage)
                ->setFilename('Online Submission')
                ->setEditableInline(true)
        );

        return $this;
    }

    /**
     * Find a file called 'online-submission' or 'Online Submission'
     * and get its content so you can pop it straight into an editor
     *
     * @return string
     */
    public function getOnlineSubmissionContent()
    {
        foreach ($this->getAllFiles() as $currentFile) {
            if ($currentFile->isOnlineSubmission()) {
                return $currentFile->getStorage()->read();
            }
        }

        return '';
    }

    /**
     * Is this a draft? (In other words, has it not yet been submitted?)
     *
     * @return bool
     */
    public function isDraft()
    {
        return !$this->isSubmitted();
    }

    /**
     * This response is submitted (no longer a draft)
     *
     * @return bool
     */
    public function isSubmitted()
    {
        /* If it's a nulldate instance, it's not submitted
         */
        if ($this->getSubmittedAt() instanceof \Alaress_Date_NullDate) {
            return false;
        }

        return $this->getSubmittedAt() !== null;
    }

    /**
     * This response has been submitted on time
     *
     * @return mixed
     */
    public function isSubmittedOnTime()
    {
        return ($this->getSubmittedAt()->lt($this->getAssessable()->getDueAt()));
    }

    /**
     * Find a file with a particular $hash
     *
     * @param $hash
     * @return File\Model
     * @throws \OutOfBoundsException
     */
    public function findFileByHash($hash)
    {
        foreach ($this->getAllFiles() as $currentFile) {
            if ($currentFile->getHash() == $hash) {
                return $currentFile;
            }
        }

        throw new \OutOfBoundsException('File not found in this response');
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
