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

abstract class AbstractResponse implements IResponse
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
        $this->setAllFeedback(new Collection());
    }

    /**
     * Find the most recent Feedback with a non-null Mark value,
     * based on its Updated field
     *
     * Assumes getAllFeedback() is sorted in reverse chronological order
     *
     * @return AbstractFeedback|null
     */
    public function findMostRecentFeedback()
    {
        if ($this->getAllFeedback()->count()) {
            return $this->getAllFeedback()->first();
        }

        return null;
    }

    /**
     * Find the most recent Published Feedback with a non-null Mark value,
     * based on its Updated field
     *
     * Assumes getAllFeedback() is sorted in reverse chronological order
     *
     * @return AbstractFeedback|null
     */
    public function findMostRecentPublishedFeedback()
    {
        if ($this->getAllFeedback()->count()) {
            $latest = $this->getAllFeedback()->first();

            foreach ($this->getAllFeedback() as $currentFeedback) {
                if ($currentFeedback->isPublished()) {
                    if ($currentFeedback->getPublishedAt()->gt($latest->getPublishedAt())) {
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
            if ($latest->isPublished()) {
                return $latest;
            }
        }

        return null;
    }

    /**
     * $user is the author of this Response
     *
     * @param User $user
     * @return bool
     */
    public function hasAuthor(User $user)
    {
        return $this->getSubmittedOnBehalfOf()->getId() == $user->getId();
    }

    /* Getters/Setters
     */

    /**
     * @return Collection|IFeedback[]
     */
    public function getAllFeedback()
    {
        return $this->allFeedback;
    }

    /**
     * @param Collection|IFeedback[] $allFeedback
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
