<?php

/**
 * Quiz
 *
 * An Adapter class to wrap around the Quiz handler which sadly
 * uses array structures. This Adapter empowers it and makes it
 * adapt to the current IAssessable behaviours
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz;

use InvalidArgumentException;
use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizReview;

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
    private $duration = 0;

    /**
     * A (HTML) message that is displayed to a participant once they have completed the quiz.
     *
     * @var string
     */
    private $completionMessage;

    /**
     * External system identifier
     *
     * @var null|string
     */
    private $externalId;


    /**
     * @return string
     */
    public static function getDefaultTypeName(): string
    {
        return 'Quiz';
    }


    /**
     * @param GradeGroup|null $gradeGroup
     * @return static
     */
    public function setGradeGroup(GradeGroup $gradeGroup = null)
    {
        if (!is_null($gradeGroup)) {
            throw new \InvalidArgumentException('Quiz cannot currently have mark type of Letter Grade.');
        }
        return $this;
    }

    /**
     * Overwrite fields from `$source` into this object.
     *
     * This implementation overwrites the additional fields over and above the
     * base implementation:
     * * completion message
     * * duration
     * * max attempts
     *
     * This implementation does _not_ overwrite:
     * * external ID: this uniquely identifies the base assessment within
     *   another system, so it should not be copied
     *
     * @param self $source
     * @return $this
     */
    public function overwrite(IAssessable $source): IAssessable
    {
        parent::overwrite($source);
        $this->completionMessage = $source->completionMessage;
        $this->duration          = $source->duration;
        $this->maxAttempts       = $source->maxAttempts;
        return $this;
    }

    /**
     * Feedback is required if latest attempt exists, is submitted, and is unreviewed.
     *
     * @inheritdoc
     */
    public function isFeedbackRequired(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        // Participant mark taken from latest attempt
        $authoritativeResponse = $participant->findMostRecentSubmittedResponse();

        // Does participant have a submitted attempt, and is it not reviewed?
        return $authoritativeResponse && !$authoritativeResponse->findMostRecentInstructorGivenPublishedFeedback();
    }

    /**
     * A Quiz takes the mark from the most recently reviewed attempt
     *
     * @inheritdoc
     */
    public function getNormalisedMark(Participant $participant, $publishedOnly)
    {
        // Determine which QuizAttempt we are taking the mark from
        $authoritativeAttempt = $this->getAuthoritativeResponse($participant, $publishedOnly);

        if (null === $authoritativeAttempt) {
            return null;
        }
        $marksAvailable = $authoritativeAttempt->getMarksAvailable();
        if (0.0 === (float)$marksAvailable) {
            return null;
        }
        return IMarkScheme::NORMALISED_MARK_MAX * $authoritativeAttempt->getMarksGiven() / $marksAvailable;
    }


    /**
     * Get the attempt responsible for the participant's mark.
     *
     * If not $publishedOnly (ie including drafts), then include un-reviewed attempts.
     * If $publishedOnly (ie not including drafts), then filter out un-reviewed attempts.
     *
     * Note that this means a draft quiz mark does not require teacher action, this is similar to self-assessable tasks.
     *
     * This is because as soon as a attempt is finished, the system auto-marks it. This is functionally
     * equivalent to the teacher providing draft marks.
     *
     * @inheritdoc
     * @return null|Attempt\Model
     */
    public function getAuthoritativeResponse(Participant $participant, $publishedOnly)
    {
        $this->assertSameAssessment($participant);

        /** @var Attempt\Model $latestAttempt */
        $latestAttempt = null;
        foreach ($participant->getAllResponses() as $attempt) {
            // Skip responses that are not submitted yet (ie still in progress)
            if (!$attempt->isSubmitted()) {
                continue;
            }

            // If published-only, skip if no published feedback
            if ($publishedOnly && !$attempt->findMostRecentInstructorGivenPublishedFeedback()) {
                continue;
            }

            // Submitted later? Or submitted at same time but created later?
            // (the latter test handles the case where cron auto-finished a bunch of attempts)
            if (
                !$latestAttempt
                || $attempt->getSubmittedAt()->gt($latestAttempt->getSubmittedAt())
                || ($attempt->getSubmittedAt()->eq($latestAttempt->getSubmittedAt()) && $attempt->getCreatedAt()->gt($latestAttempt->getCreatedAt()))
            ) {
                $latestAttempt = $attempt;
            }
        }
        return $latestAttempt;
    }

    /**
     * @inheritdoc
     */
    public function getAuthoritativeFeedback(Participant $participant, $publishedOnly)
    {
        return $publishedOnly
            ? $participant->findMostRecentInstructorGivenPublishedFeedback()
            : $participant->findMostRecentInstructorGivenFeedback();
    }

    /**
     * @inheritdoc
     */
    public function hasPendingDraftAuthoritativeFeedback(Participant $participant): bool
    {
        // Quizzes do not have draft marks
        $this->assertSameAssessment($participant);
        return false;
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
     * @throws InvalidArgumentException if the dates are not validly set
     * @return void
     */
    public function assertValidDates()
    {
        /* Check publish date against everything else
        /* Don't check dates if in course */
        if ($this->getBaseContext() instanceof UnitSpecification) {
            return;
        }

        $this->assertDateSequence([
            'publish' => $this->getPublishAt(),
            'open'    => $this->getOpenAt(),
            'due'     => $this->getDueAt(),
            'late'    => $this->getCloseAt()
        ]);
    }

    /**
     * @inheritdoc
     */
    public function canLearnerCreateResponse(User $learner)
    {
        // Not open ?
        if (!$this->isOpen($learner)) {
            return false;
        }

        $participant = $this->getParticipantIfExtant($learner);

        // Not a participant ?
        if (!$participant) {
            return false;
        }

        if ($this->getMaxAttempts() === self::ATTEMPTS_UNLIMITED) {
            return true;
        }

        /* Responses less than maximum allowed
         */
        if ($participant->getAllResponses()->count() < $this->getMaxAttempts()) {
            return true;
        }

        /* Response count is the same as the maximum allowed
         */
        if ($participant->getAllResponses()->count() === $this->getMaxAttempts()) {
            $mostRecentResponse = $participant->findMostRecentResponse();

            /* Check whether the most recent response is officially submitted
             * You should be allowed to resume the draft/in-progress attempt
             */
            if ($mostRecentResponse && !$mostRecentResponse->isSubmitted()) {
                return true;
            }
        }

        // Participant has exceeded attempt count
        return false;
    }

    /**
     * Quizzes do not support instructors submitting on behalf of learners:
     * otherwise, the rules are the same.
     *
     * @param User $instructor
     * @param User $submittedOnBehalfOf
     * @return bool
     */
    public function canInstructorCreateResponse(User $instructor, User $submittedOnBehalfOf)
    {
        if (parent::canInstructorCreateResponse($instructor, $submittedOnBehalfOf)) {
            return $instructor->getId() == $submittedOnBehalfOf->getId();
        }
        return false;
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
     * Is this quiz unlimited duration?
     *
     * @return bool
     */
    public function isDurationUnlimited(): bool
    {
        return $this->duration === self::DURATION_UNLIMITED;
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
     * Does this quiz allow unlimited attempts?
     *
     * @return bool
     */
    public function isAttemptsUnlimited(): bool
    {
        return $this->maxAttempts === self::ATTEMPTS_UNLIMITED;
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

    /**
     * @inheritdoc
     */
    public function getResponseClass()
    {
        return QuizAttempt::class;
    }

    /**
     * @inheritdoc
     */
    public function getFeedbackClass()
    {
        return QuizReview::class;
    }


    /**
     * @return string|null
     */
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }


    /**
     * @param string|null $externalId
     * @return $this
     */
    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }
}
