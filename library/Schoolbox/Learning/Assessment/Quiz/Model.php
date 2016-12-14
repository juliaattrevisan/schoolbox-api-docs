<?php
/**
 * Quiz
 *
 * An Adapter class to wrap around the Quiz handler which sadly
 * uses array structures. This Adapter empowers it and makes it
 * adapt to the current IAssessable behaviours
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Quiz;

use \Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\User;

class Model extends AbstractAssessment
{

    /**
     * Possible value for `$this->maxAttempts`.
     *
     * This value specifies that a participant may make an unlimited number of attempts at this quiz.
     *
     * @var int
     */
    const ATTEMPTS_UNLIMITED = 0;

    /**
     * Possible value for `$this->duration`.
     *
     * This value specifies that a participant may take an unlimited amount of time to complete this quiz.
     *
     * @var int
     */
    const DURATION_UNLIMITED = -1;

    /**
     * What's the maximum number of attempts a participant may make on this quiz?
     *
     * @var int
     */
    protected $maxAttempts;

    /**
     * How long (in seconds) does a participant have to complete this quiz, once their attempt has started?
     *
     * @var int
     */
    protected $duration = 0;

    /**
     * A (HTML) message that is displayed to a participant once they have completed the quiz.
     *
     * @var string
     */
    protected $completionMessage;

    /**
     * @param GradeGroup|null $gradeGroup
     * @return static
     */
    public function setGradeGroup(GradeGroup $gradeGroup = null)
    {
        if(!is_null($gradeGroup)){
            throw new \InvalidArgumentException('Quiz cannot currently have mark type of Letter Grade.');
        }
        return $this;
    }

    /**
     * @param User $user
     * @param bool $publishedOnly
     * @return null|string
     */
    public function getHumanReadableMark(User $user, $publishedOnly)
    {
        $markScheme = $this->getMarkScheme();

        // Special Case: If a Quiz has sections with questions pools, the total marks available may change per attempt
        if ($markScheme instanceof TotalMarkFractional) {
            $participant = $this->getParticipant($user);

            $feedback = $publishedOnly
                ? $participant->findMostRecentPublishedFeedback()
                : $participant->findMostRecentFeedback();

            $response = $feedback
                ? $feedback->getResponse()
                : null;

            // Response
            if (!isset($response)) {
                return null;
            }

            return $markScheme->getHumanReadableMark($this->getNormalisedMark($user, $publishedOnly), $response);
        }

        return parent::getHumanReadableMark($user, $publishedOnly);
    }

    /**
     * The number of hours and minutes that is represented by getDuration()
     * (which is the duration represented in seconds)
     */
    public function getDurationComponents()
    {
        if ($this->getDuration()) {
            return [
                'hours'   => floor($this->getDuration() / 3600),
                'minutes' => ($this->getDuration() % 3600) / 60
            ];
        }

        return [];
    }

    /**
     * Check the dates set on this model are valid
     * @return bool
     */
    public function isValidDates()
    {
        /* Check publish date against everything else
        /* Don't check dates if in course */
        if(! $this->getBaseContext() instanceof  UnitSpecification ) {

            if ($this->getPublishAt()->gt($this->getOpenAt()) || $this->getPublishAt()->gt($this->getDueAt()) || $this->getPublishAt()->gt($this->getCloseAt())) {
                return false;
            }

            /* Check open date against everything else
             */
            if ($this->getOpenAt()->gt($this->getDueAt()) || $this->getOpenAt()->gt($this->getCloseAt())) {
                return false;
            }

            /* Check due date against everything else
             */
            if ($this->getDueAt()->gt($this->getCloseAt())) {
                return false;
            }
        }
        return true;
    }

    /* Getters/Setters
     */

    /**
     * @inheritdoc
     */
    public function getFamilyName()
    {
        return 'quiz';
    }

    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getTypeName()
    {
        return 'Quiz';
    }

    /**
     * @return int
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * @param int $duration
     * @return static
     */
    public function setDuration($duration)
    {
        $this->duration = $duration;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxAttempts()
    {
        return $this->maxAttempts;
    }

    /**
     * @param int $maxAttempts
     * @return static
     */
    public function setMaxAttempts($maxAttempts)
    {
        $this->maxAttempts = $maxAttempts;
        return $this;
    }

    /**
     * @return string
     */
    public function getCompletionMessage()
    {
        return $this->completionMessage;
    }

    /**
     * @param string $completionMessage
     * @return static
     */
    public function setCompletionMessage($completionMessage)
    {
        $this->completionMessage = $completionMessage;
        return $this;
    }
}
