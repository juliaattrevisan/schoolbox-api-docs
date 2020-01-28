<?php
/**
 * AbstractFeedback
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\User;
use \Schoolbox\Mapper\Traits\Model\Id;
use \Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use \Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use \Schoolbox\Mapper\Traits\Model\DeletedAtDate;

abstract class AbstractFeedback implements IFeedback
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
     * The IResponse to which this Feedback relates
     *
     * @var IResponse|null
     */
    private $response;

    /**
     * Mark given to this Assessable
     *
     * @var string
     */
    protected $humanReadableMark;

    /**
     * The mark out of 100 given to this Assessable
     *
     * @var float
     */
    protected $normalisedMark;

    /**
     * @var string
     */
    protected $comment;

    /**
     * The person for whom this Feedback was created for.
     * Usually a Student.
     *
     * @var User
     */
    protected $reviewee;

    /**
     * The person who reviewed (created) this Feedback
     * Typically a Teacher
     *
     * @var User
     */
    protected $reviewer;

    /**
     * The datetime at which this Feedback was made visible to Students
     *
     * @todo Remove when implemented. Special case for Quiz:
     *     if(assess_attempt.reviewed_at){
     *         $publishedAt = assess_attempt.reviewed_at
     *     } else if (all questions in attempt are automarked_already) {
     *         $publishedAt = assess_attempt.finish
     *     } else {
     *         $publishedAt = null
     *     }
     *
     * @var \Alaress_Date_Format
     */
    protected $publishedAt = null;

    /**
     * Determine whether this Feedback has been published.
     * If the publish date is null, it's a draft
     *
     * @return bool
     */
    public function isPublished()
    {
        /* If it's a nulldate instance, it's not published
         */
        if ($this->getPublishedAt() instanceof \Alaress_Date_NullDate) {
            return false;
        }

        return $this->getPublishedAt() !== null;
    }


    /**
     * @inheritdoc
     */
    public function isDraft()
    {
        return !$this->isPublished();
    }


    /* Getters/Setters
     */

    /**
     * @return string
     */
    public function getHumanReadableMark()
    {
        return $this->humanReadableMark;
    }

    /**
     * @param string $humanReadableMark
     * @return $this
     */
    public function setHumanReadableMark($humanReadableMark)
    {
        $this->humanReadableMark = $humanReadableMark;
        return $this;
    }

    /**
     * @return float|null
     */
    public function getNormalisedMark()
    {
        return $this->normalisedMark;
    }

    /**
     * @param null|float $normalisedMark
     * @return $this
     */
    public function setNormalisedMark($normalisedMark)
    {
        $this->normalisedMark = $normalisedMark;
        return $this;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param string $comment
     * @return $this
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return User
     */
    public function getReviewee()
    {
        return $this->reviewee;
    }

    /**
     * @param User $reviewee
     * @return static
     */
    public function setReviewee($reviewee)
    {
        $this->reviewee = $reviewee;
        return $this;
    }

    /**
     * @return User
     */
    public function getReviewer()
    {
        return $this->reviewer;
    }

    /**
     * @param User $reviewer
     * @return $this
     */
    public function setReviewer($reviewer)
    {
        $this->reviewer = $reviewer;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getPublishedAt()
    {
        return $this->publishedAt;
    }

    /**
     * @param \Alaress_Date_Format $publishedAt
     * @return $this
     */
    public function setPublishedAt($publishedAt)
    {
        $this->publishedAt = $publishedAt;
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

    /**
     * @return IResponse
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param IResponse $response
     * @return static
     */
    public function setResponse(IResponse $response = null)
    {
        $this->response = $response;
        return $this;
    }
}
