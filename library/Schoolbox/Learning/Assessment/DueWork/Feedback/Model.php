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
     * The author of the feedback is the same as the person who receives it
     * The feedback has normalised marks
     *
     * @return bool
     */
    public function isSelfAssessed(): bool
    {
        $reviewerIsSubmissionAuthor = ($this->getReviewee()->getId() === $this->getReviewer()->getId());
        $feedbackHasNormalisedMarks = $this->getNormalisedMark() !== null;

        return $reviewerIsSubmissionAuthor && $feedbackHasNormalisedMarks;
    }

    /**
     * The author of the feedback is the same as the person who receives it
     * The feedback does not have normalised marks
     *
     * @return bool
     */
    public function isSelfReflected(): bool
    {
        $reviewerIsSubmissionAuthor = ($this->getReviewee()->getId() === $this->getReviewer()->getId());
        $feedbackHasNormalisedMarks = $this->getNormalisedMark() === null;

        return $reviewerIsSubmissionAuthor && $feedbackHasNormalisedMarks;
    }
}
