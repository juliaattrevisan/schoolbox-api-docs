<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\Collection;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Learning\Assessment\DueWork\Feedback;
use Schoolbox\Learning\Assessment\AbstractResponse;

/**
 * Class Model
 *
 * @method Collection|Feedback\Model[] getAllFeedback()
 */
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
     * @param string $body
     * @return $this
     * @throws \Schoolbox\File\Exception
     * @throws \Schoolbox\Mapper\Exception
     */
    public function attachOnlineSubmissionContent($body)
    {
        $storage = \Schoolbox\Resource\Storage\Mapper::getInstance()->acceptStream('online-submission.html', $body, false);

        $this->getAllFiles()->add(
            SingleFile\Mapper::getInstance()->createModel()
                ->setStorage($storage)
                ->setFilename('Online Submission')
                ->setEditableInline(true)
        );

        return $this;
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
     * Did the student submit this work offline, and this is just the acknowledgement
     *
     * @return bool
     */
    public function isSubmittedOffline(): bool
    {
        return $this->allFiles->count() === 0;
    }

    /**
     * Find a file with a particular $hash
     *
     * @param string $hash
     * @return SingleFile\Model
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
     * @return Collection|SingleFile\Model[]
     */
    public function getAllFiles()
    {
        return $this->allFiles;
    }

    /**
     * @param Collection|SingleFile\Model[] $allFiles
     * @return self
     */
    public function setAllFiles($allFiles)
    {
        $this->allFiles = $allFiles;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getSubmittedAt()
    {
        return $this->submittedAt;
    }

    /**
     * @param mixed $submittedAt
     * @return $this
     */
    public function setSubmittedAt(Alaress_Date_Format $submittedAt = null)
    {
        $this->submittedAt = $submittedAt ?: new Alaress_Date_NullDate();
        return $this;
    }
}
