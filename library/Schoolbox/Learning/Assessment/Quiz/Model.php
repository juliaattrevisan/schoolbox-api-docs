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
use RuntimeException;
use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Learnosity\Helper;
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
    const DURATION_UNLIMITED = 0;


    /**
     * Learnosity reference identifier, to associate our quiz
     * with a Learnosity quiz.
     *
     * @var string|null
     */
    private ?string $learnosityReference = null;


    /**
     * The Learnosity job reference identifier which associates an import of
     * Learnosity data from one quiz to another.
     *
     * @var string|null
     */
    private ?string $learnosityJobReference = null;


    /**
     * The status of the Learnosity job: if set, this is one of the
     * `self::LEARNOSITY_JOB_STATUS_*` constants.
     */
    private ?string $learnosityJobStatus = null;


    /**
     * The number of attempts of this Learnosity job.
     */
    private ?int $learnosityJobAttemptCount = null;


    /**
     * The assessment ID from which this assessment's Learnosity data was
     * copied, if any.
     */
    private ?int $learnositySourceAssessmentId = null;


    /**
     * Decide whether to randomise all the items in a learnosity quiz
     *
     * @var int
     */
    protected $shuffleItems;


    /**
     * More security-conscious implementation of a learnosity quiz
     *
     * @var int
     */
    protected $promptsMode;


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
     * This quiz's reading time duration
     *
     * @var int
     */
    private $readingTimeDuration = 0;


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
     * This quiz's clone does not inherit the source object's Learnosity
     * reference: the Learnosity data will be copied in a separate step.
     *
     * @return static
     */
    public function cloneForCopy()
    {
        /** @var static $clone */
        $clone = parent::cloneForCopy();
        $clone->setLearnosityReference(null);
        $clone->setLearnosityJobReference(null);
        return $clone;
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
     * This implementation clears the Learnosity reference: the Learnosity data
     * will be copied from `$source` in a separate step.
     *
     * @param self $source
     * @return $this
     */
    public function overwrite(IAssessable $source): IAssessable
    {
        parent::overwrite($source);
        $this->completionMessage      = $source->completionMessage;
        $this->duration               = $source->duration;
        $this->maxAttempts            = $source->maxAttempts;
        $this->learnosityReference    = null;
        $this->learnosityJobReference = null;
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

        return $authoritativeAttempt->getNormalisedMark();
    }

    /**
     * Quiz does not use the MarkScheme. It merely returns the cached value.
     *
     * @inheritdoc
     */
    public function getHumanReadableMark(Participant $participant, $publishedOnly)
    {
        $feedback = $this->getAuthoritativeFeedback($participant, $publishedOnly);
        return $feedback ? $feedback->getHumanReadableMark() : null;
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
        /** @var Attempt\Model|null $latestAttempt */
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
     * Takes $duration seconds and converts it to an (hours, minutes) tuple
     *
     * The number of hours and minutes that is represented by getDuration()
     * (which is the duration represented in seconds)
     *
     * @param int|null $duration
     * @return array
     */
    public function getDurationComponents(?int $duration): array
    {
        if ($duration && $duration > 0) {
            return [
                'hours'   => floor($duration / 3600),
                'minutes' => ($duration % 3600) / 60
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
     * By default: all assessments are usable.
     */
    public function isUsable(): bool
    {
        try {
            $this->assertUsable();
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Assert that this quiz:
     * * has been imported into Learnosity
     * * if it's being duplicated: that that duplication process has completed
     */
    public function assertUsable(): void
    {
        if ('' === (string)$this->getLearnosityReference()) {
            throw new RuntimeException(
                sprintf(
                    'Assessment %s is not in learnosity; try importing it with the cli tool',
                    $this->getId()
                )
            );
        }

        // Is there a duplication job? If so block until completed
        if ($this->getLearnosityJobReference()) {
            $jobStatus = $this->getLearnosityJobStatus();
            if ($jobStatus === Helper::LEARNOSITY_PROCESS_STATUS_FAILED) {
                throw new RuntimeException(
                    sprintf(
                        'Assessment %s duplication process has failed; please contact support',
                        $this->getId()
                    )
                );
            }
            if ($jobStatus !== Helper::LEARNOSITY_PROCESS_STATUS_COMPLETED) {
                throw new RuntimeException(
                    sprintf(
                        'Assessment %s duplication process has not yet completed; try again later',
                        $this->getId()
                    )
                );
            }
        }
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


    /**
     * @inheritDoc
     */
    public function isCustomParticipationCopyable(): bool
    {
        /* At the moment, we want to restrict the copying of custom participation
         * for quizzes, which will be handled in the future, so for the time being, we will leave it out.
         */
        return false;
    }


    /* Getters/Setters
     */

    /**
     * @inheritdoc
     */
    public static function getFamilyName()
    {
        return AssessmentType::TYPE_QUIZ;
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
     * @return int|null
     */
    public function getReadingTimeDuration(): ?int
    {
        return $this->readingTimeDuration;
    }

    /**
     * @param int|null $readingTimeDuration
     * @return static
     */
    public function setReadingTimeDuration(?int $readingTimeDuration): self
    {
        $this->readingTimeDuration = $readingTimeDuration;
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
     * @return int
     */
    public function getShuffleItems()
    {
        return $this->shuffleItems;
    }

    /**
     * @param int $shuffleItems
     * @return $this
     */
    public function setShuffleItems($shuffleItems)
    {
        $this->shuffleItems = $shuffleItems;
        return $this;
    }

    /**
     * @return int
     */
    public function getPromptsMode()
    {
        return $this->promptsMode;
    }

    /**
     * @param $promptsMode
     * @return $this
     */
    public function setPromptsMode($promptsMode)
    {
        $this->promptsMode = $promptsMode;
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


    /**
     * @return string|null
     */
    public function getLearnosityReference(): ?string
    {
        return $this->learnosityReference;
    }


    /**
     * @param string|null $learnosityReference
     * @return Model
     */
    public function setLearnosityReference(?string $learnosityReference): self
    {
        $this->learnosityReference = $learnosityReference;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getLearnosityJobReference(): ?string
    {
        return $this->learnosityJobReference;
    }


    /**
     * @param string|null $learnosityJobReference
     * @return Model
     */
    public function setLearnosityJobReference(?string $learnosityJobReference): self
    {
        $this->learnosityJobReference = $learnosityJobReference;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getLearnosityJobStatus(): ?string
    {
        return $this->learnosityJobStatus;
    }


    /**
     * @param string|null $learnosityJobStatus
     * @return $this
     */
    public function setLearnosityJobStatus(?string $learnosityJobStatus): self
    {
        $this->learnosityJobStatus = $learnosityJobStatus;
        return $this;
    }


    /**
     * @return int|null
     */
    public function getLearnosityJobAttemptCount(): ?int
    {
        return $this->learnosityJobAttemptCount;
    }


    /**
     * @param int|null $learnosityJobAttemptCount
     * @return $this
     */
    public function setLearnosityJobAttemptCount(?int $learnosityJobAttemptCount): self
    {
        $this->learnosityJobAttemptCount = $learnosityJobAttemptCount;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getLearnositySourceAssessmentId(): ?int
    {
        return $this->learnositySourceAssessmentId;
    }


    /**
     * @param int|null $learnositySourceAssessmentId
     * @return $this
     */
    public function setLearnositySourceAssessmentId(?int $learnositySourceAssessmentId): self
    {
        $this->learnositySourceAssessmentId = $learnositySourceAssessmentId;
        return $this;
    }
}
