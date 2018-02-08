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
use Schoolbox\Learning\Assessment\Feedback\InstructorGivenFilter;
use Schoolbox\Learning\Assessment\Feedback\SelfAssessmentFilter;
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
     * @var Collection|IFeedback[]
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
     * @inheritdoc
     */
    public function findMostRecentInstructorGivenFeedback(): ?IFeedback
    {
        return $this->getAllFeedback()
                    ->filter(new InstructorGivenFilter())
                    ->sortBy('updatedAt', 'desc')
                    ->first();
    }

    /**
     * @inheritdoc
     */
    public function findMostRecentPublishedInstructorGivenFeedback(): ?IFeedback
    {
        return $this->getAllFeedback()
                    ->filter(new InstructorGivenFilter())
                    ->filterBy('isPublished', true)
                    ->sortBy('publishedAt', 'desc')
                    ->first();
    }

    /**
     * @inheritdoc
     */
    public function findMostRecentSelfAssessedFeedback(): ?IFeedback
    {
        return $this->getAllFeedback()
                    ->filter(new SelfAssessmentFilter())
                    ->sortBy('updatedAt', 'desc')
                    ->first();
    }

    /**
     * @inheritdoc
     */
    public function findMostRecentSelfAssessedPublishedFeedback(): ?IFeedback
    {
        return $this->getAllFeedback()
                    ->filter(new SelfAssessmentFilter())
                    ->filterBy('isPublished', true)
                    ->sortBy('publishedAt', 'desc')
                    ->first();
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
    public function setSubmittedBy(User $submittedBy)
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
     * Was this response submitted on time?
     * If the response is not submitted; this will always return false.
     * Therefore:
     *   - "is the response submitted": response.submitted
     *   - "is the response late":      response.submitted AND NOT response.submittedOnTime
     *   - "is the response on time":   response.submitted AND response.submittedOnTime
     *
     * You must not rely on this method without also checking the submission status.
     *
     * @return bool
     */
    public function isSubmittedOnTime()
    {
        if (!$this->isSubmitted()) {
            return false;
        }
        // assessment.dueAt > response.submittedAt
        return $this->getAssessable()->getDueAt()->gt($this->getSubmittedAt());
    }

    /**
     * Does this IResponse have feedback?
     *
     * @return bool
     */
    public function hasFeedback()
    {
        return $this->allFeedback->count() > 0;
    }

    /**
     * @inheritdoc
     */
    public function hasPublishedFeedback()
    {
        foreach($this->allFeedback as $feedback){
            if($feedback->isPublished()){
                return true;
            }
        }
        return false;
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
    public function setSubmittedOnBehalfOf(User $submittedOnBehalfOf)
    {
        $this->submittedOnBehalfOf = $submittedOnBehalfOf;
        return $this;
    }
}
