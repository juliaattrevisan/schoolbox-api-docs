<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use \Schoolbox\Collection;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Learning\Assessment\DueWork\Feedback;
use UnexpectedValueException;
use \Schoolbox\Learning\Assessment\AbstractResponse;

/**
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
     * @param $body
     * @return $this
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
     * Find a file called 'online-submission' or 'Online Submission'
     * and get its content so you can pop it straight into an editor
     *
     * @return string
     */
    public function getOnlineSubmissionContent()
    {
        foreach ($this->getAllFiles() as $currentFile) {
            if ($currentFile->getEditableInline()) {
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
     * Find a file with a particular $hash
     *
     * @param $hash
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


    /**
     * Returns the most recent published self-reflected feedback for this submission
     *
     * @return null|Feedback\Model
     * @throws UnexpectedValueException  If due-work feedback object property is not hydrated
     */
    public function findMostRecentPublishedSelfReflectedFeedback(): ?Feedback\Model
    {
        /** @var Feedback\Model $mostRecentFeedback */
        $mostRecentFeedback = null;
        foreach ($this->getAllFeedback() as $feedback) {
            if (!$feedback->isPublished() || !$feedback->isSelfAssessment()) {
                continue;
            }
            if (!$mostRecentFeedback || $feedback->getPublishedAt()->gt($mostRecentFeedback->getPublishedAt())) {
                $mostRecentFeedback = $feedback;
            }
        }
        return $mostRecentFeedback;
    }
}
