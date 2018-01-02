<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Task\Feedback;

use \Schoolbox\Learning\Assessment\Task\Model as Task;
use \Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;
use Schoolbox\Learning\MarkScheme\Completion;
use Schoolbox\User;

/**
 * @method TaskResponse getResponse()
 */
class Model extends AbstractFeedback
{

    /**
     * @return Task
     */
    public function getAssessable()
    {
        return $this->getResponse()->getAssessable();
    }

    /**
     * @param IAssessable $assessable
     * @return $this
     */
    public function setAssessable($assessable)
    {
        $this->getResponse()->setAssessable($assessable);
        return $this;
    }

    /**
     * Intentionally no setter(). The value is derive from the IResponse object.
     *
     * Create a new TaskResponse if you wish to change a student's completion status
     *
     * @return bool
     */
    public function getMarkedComplete()
    {
        return $this->getResponse()->getMarkedComplete();
    }

    /**
     * Convenience method.
     *
     * Intentionally no setter(). That would potentially require implicit mutation of the submittedBy and submittedFor values.
     *
     * @return int
     */
    public function getCompletionStatus()
    {
        return $this->getResponse()->getCompletionStatus();
    }


    /**
     * Determine whether this Feedback has been published.
     * If the publish date is null, it's a draft
     *
     * @return bool
     */
    public function isPublished()
    {
        return true;
    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->getResponse()->getId();
    }


    /**
     * @param  int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->getResponse()->setId($id);
        return $this;
    }


    /**
     * Get the human-readable representation of the Normalised Mark
     *
     * This returns a string which could be something like "Not Submitted"
     * or "B+" or "32/36"
     *
     * @return string
     */
    public function getHumanReadableMark()
    {
        return Completion::HUMAN_READABLE_COMPLETE;
    }


    /**
     * Set the human-readable representation of the Normalised Mark
     *
     * @param string $humanReadableMark
     * @return $this
     */
    public function setHumanReadableMark($humanReadableMark)
    {
        throw new \BadMethodCallException('You cannot set a TaskFeedback HumanReadable Mark.');
    }

    /**
     * Calculate the final mark for this IAssessable
     *
     * This object's existence indicates a Teacher has marked the task as 'Complete'.
     *
     * While logically this equates to IMarkScheme::NORMALISED_MARK_MAX, for now we
     * return null to simplify mark summation logic.
     *
     * @return null
     */
    public function getNormalisedMark()
    {
        return null;
    }


    /**
     * Set the Normalised Mark for an IFeedback
     *
     * @param float $normalisedMark
     */
    public function setNormalisedMark($normalisedMark)
    {
        throw new \BadMethodCallException('You cannot set a TaskFeedback Normalised Mark.');
    }


    /**
     * Get the comment associated with this IFeedback
     *
     * @return string
     */
    public function getComment()
    {
        // Tasks do not support comments for now
        return '';
    }


    /**
     * Set the comment associated with this IFeedback
     *
     * @param string $comment
     */
    public function setComment($comment)
    {
        throw new \BadMethodCallException('You cannot set a TaskFeedback Comment.');
    }


    /**
     * Get the person for whom this IFeedback was created
     *
     * Typically a student
     *
     * @return User
     */
    public function getReviewee()
    {
        return $this->getResponse()->getSubmittedOnBehalfOf();
    }


    /**
     * Set the person for whom this IFeedback was created
     *
     * @param User $reviewee
     * @return $this
     */
    public function setReviewee($reviewee)
    {
        $this->getResponse()->setSubmittedOnBehalfOf($reviewee);
        return $this;
    }


    /**
     * Get the person who created this IFeedback
     *
     * Typically a teacher
     *
     * @return User
     */
    public function getReviewer()
    {
        return $this->getResponse()->getSubmittedBy();
    }


    /**
     * Set the person who created this IFeedback
     *
     * @param User $reviewer
     * @return $this
     */
    public function setReviewer($reviewer)
    {
        $this->getResponse()->setSubmittedBy($reviewer);
        return $this;
    }

    /**
     * @param IResponse|TaskResponse $response
     * @return static
     */
    public function setResponse(IResponse $response = null)
    {
        if(is_null($response)){
            throw new \InvalidArgumentException('TaskFeedbacks must always link to a TaskResponse');
        }
        parent::setResponse($response);
        return $this;
    }


    /**
     * TaskFeedback is published when the TaskResponse was created.
     *
     * @return \Alaress_Date_Format
     */
    public function getPublishedAt()
    {
        return $this->getResponse()->getCreatedAt();
    }


    /**
     * Set the date when this IFeedback was made accessible to respondents
     *
     * @param \Alaress_Date_Format $publishedAt
     * @return $this
     */
    public function setPublishedAt($publishedAt)
    {
        // Ignored. Task Feedback are always published.
        return $this;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->getResponse()->getCreatedAt();
    }

    /**
     * @param  \Alaress_Date_Format $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(\Alaress_Date_Format $createdAt = null)
    {
        $this->getResponse()->setCreatedAt($createdAt);
        return $this;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt()
    {
        return $this->getResponse()->getUpdatedAt();
    }


    /**
     * @param  \Alaress_Date_Format $updatedAt
     *
     * @return $this
     */
    public function setUpdatedAt(\Alaress_Date_Format $updatedAt = null)
    {
        $this->getResponse()->setUpdatedAt($updatedAt);
        return $this;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->getResponse()->getDeletedAt();
    }


    /**
     * @param  \Alaress_Date_Format $deletedAt
     * @return $this
     */
    public function setDeletedAt(\Alaress_Date_Format $deletedAt = null)
    {
        $this->getResponse()->setDeletedAt($deletedAt);
        return $this;
    }
}
