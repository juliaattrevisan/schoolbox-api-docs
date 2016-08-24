<?php
/**
 * AbstractResponse
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\Mapper\Traits\Model\Id;
use \Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use \Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use \Schoolbox\Mapper\Traits\Model\DeletedAtDate;

class AbstractResponse implements IResponse
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * The IAssessable to which this relates
     *
     * @var IAssessable
     */
    protected $assessable;

    /**
     * @var Collection
     */
    protected $allFeedback;

    /**
     * The person who created this Submission
     *
     * Teachers can submit on behalf of a Student - the Teacher
     * is the User instance here
     *
     * @var User
     */
    protected $submittedBy;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        $this->setAllFeedback(new Collection());
    }

    /**
     * Find the most recent Feedback with a non-null Mark value,
     * based on its Updated field
     *
     * Assumes getAllFeedback() is sorted in reverse chronological order
     */
    public function findMostRecentFeedback()
    {
        if ($this->getAllFeedback()->count()) {
            $latest = $this->getAllFeedback()->first();

            foreach ($this->getAllFeedback() as $currentFeedback) {
                if (!$currentFeedback->isNullMark()) {
                    return $currentFeedback;
                }
            }

            /* If the ->first() instance is actually null
             * and all the other Feedbacks are also null
             * return null
             *
             * Otherwise return the Feedback itself
             */
            if (!$latest->isNullMark()) {
                return $latest;
            }
        }

        return null;
    }

    /**
     * Find the most recent Published Feedback with a non-null Mark value,
     * based on its Updated field
     *
     * Assumes getAllFeedback() is sorted in reverse chronological order
     */
    public function findMostRecentPublishedFeedback()
    {
        if ($this->getAllFeedback()->count()) {
            $latest = $this->getAllFeedback()->first();

            foreach ($this->getAllFeedback() as $currentFeedback) {
                if ($currentFeedback->getPublishedAt()) {
                    if ($currentFeedback->getPublishedAt()->gt($latest->getPublishedAt()) && !$currentFeedback->isNullMark()) {
                        $latest = $currentFeedback;
                    }
                }
            }

            /* If the ->first() instance is actually null
             * and all the other Feedbacks are also null
             * return null
             *
             * Otherwise return the Feedback itself
             */
            if (!$latest->isNullMark()) {
                return $latest;
            }
        }

        return null;
    }

    /* Getters/Setters
     */

    /**
     * @return Collection
     */
    public function getAllFeedback()
    {
        return $this->allFeedback;
    }

    /**
     * @param Collection $allFeedback
     * @return $this
     */
    public function setAllFeedback($allFeedback)
    {
        $this->allFeedback = $allFeedback;
        return $this;
    }

    /**
     * @return User
     */
    public function getSubmittedBy()
    {
        return $this->submittedBy;
    }

    /**
     * @param User $submittedBy
     * @return $this
     */
    public function setSubmittedBy($submittedBy)
    {
        $this->submittedBy = $submittedBy;
        return $this;
    }

    /**
     * @return IAssessable
     */
    public function getAssessable()
    {
        return $this->assessable;
    }

    /**
     * @param IAssessable $assessable
     * @return $this
     */
    public function setAssessable($assessable)
    {
        $this->assessable = $assessable;
        return $this;
    }
}