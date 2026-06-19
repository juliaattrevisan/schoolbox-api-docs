<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent;

use InvalidArgumentException;
use Schoolbox\Admin\DialogAgent\Scenario\Field\Context\Mapper as FieldContextMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DialogAgent\Lookup\Helpfulness;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\User;

class Model extends AbstractAssessment
{
    /**
     * This appears on Create Activity screens but you probably need to rename
     * this to something friendlier because it's user-facing.
     *
     * TODO Rename to whatever this is canonically called
     */
    private const string DEFAULT_TYPE_NAME = 'Discussion';


    /**
     * Possible value for `$this->duration`.
     *
     * This value specifies that a participant may take an unlimited amount of time to complete this activity.
     */
    public const int DURATION_UNLIMITED = 0;


    /**
     * @var int|null
     */
    private ?int $scenarioId = null;


    /**
     * The IAssessable associated with this Dialog Agent
     */
    private ?AssessmentModel $assessmentModel = null;


    /**
     * @var string|null
     */
    private ?string $language = null;


    /**
     * @var Helpfulness|null
     */
    private ?Helpfulness $helpfulness = null;


    /**
     * @var bool|null
     */
    private ?bool $useDifferentiationProfile = null;


    /**
     * @var string|null
     */
    private ?string $openingMessage = null;


    /**
     * @var string|null
     */
    private ?string $rules = null;


    /**
     * @var string|null
     */
    private ?string $prompt = null;


    /**
     * @var int|null
     */
    private ?int $duration = null;


    /**
     * The user who created this Dialog Agent
     *
     * @var User|null
     */
    private ?User $createdBy = null;


    /**
     * The user who most recently updated this Dialog Agent
     *
     * @var User|null
     */
    private ?User $updatedBy = null;


    /**
     * The user who deleted this Dialog Agent
     *
     * @var User|null
     */
    private ?User $deletedBy = null;


    /**
     * @return string
     */
    public static function getDefaultTypeName(): string
    {
        return self::DEFAULT_TYPE_NAME;
    }


    /**
     * Decide if $learner can create a response to this Dialog Agent.
     *
     * @param User $learner
     * @return bool
     */
    public function canLearnerCreateResponse(User $learner): bool
    {
        // Not a participant?
        if (!$this->isParticipant($learner)) {
            return false;
        }

        // Not open?
        if (!$this->isOpen($learner)) {
            return false;
        }

        /* Check for full configuration via any mapper's internal db reference
         *
         * This is a bit gruesome but there seems to be no other way obtain such an instance without changing the entire
         * swamp usage o this method. Anyway, I chose the most appropriate mapper possible.
         */
        $fieldContextCheck = new ValidConfiguration(FieldContextMapper::getInstance()->getDatabase());

        if (!$fieldContextCheck->isSatisfiedBy($this)) {
            return false;
        }

        /* Note that durations are per response and are enforced by NextRoutes and the frontend for
         * the relevant routes which shouldn't be possible to get to when duration has been exceeded.
         *
         * Backend implements a crontab that forcibly shuts whatever the frontend couldn't.
         * The backend also enforces no more messages after duration is exceeded, but still allows submission.
         *
         * If you implemented the check here, you'd prevent students (and potentially some instructors) from
         * submitting responses that had exceeded duration whilst in progress, should be fine without that.
         */

        /* Check authoritative response is submitted
         * If it is, you can't make any more responses: you need staff to unlock it again for you
         */
        $authoritativeResponse = $this->getAuthoritativeResponse($this->getParticipantIfExtant($learner), false);

        if ($authoritativeResponse && $authoritativeResponse->isSubmitted()) {
            return false;
        }

        /* Make a new thing or resume an unsubmitted thing
         */
        return true;
    }


    /**
     * This model's "kind of assessment" used to identify it
     * among the family of AbstractAssessment subclasses
     *
     * @return string
     */
    public static function getFamilyName(): string
    {
        return AssessmentType::TYPE_DIALOG_AGENT;
    }


    /**
     * @inheritdoc
     */
    public function canInstructorCreateResponse(User $instructor, User $submittedOnBehalfOf)
    {
        // Project containers cannot have IResponses directly on them. Only their sub-assessments can.
        return false;
    }


    /**
     * Dialog Agents never have marks.
     *
     * @param Participant $participant
     * @param $publishedOnly
     * @return float|null
     */
    public function getNormalisedMark(Participant $participant, $publishedOnly): ?float
    {
        return null;
    }


    /**
     * Decide which Dialog Agent conversation made by students should be considered canonical.
     * Note that we have a bool to decide which one should be considered authoritative, rather
     * than simply depend on time such as "most recent conversation"
     *
     * @param Participant $participant
     * @param $publishedOnly
     * @return IResponse|null
     */
    public function getAuthoritativeResponse(Participant $participant, $publishedOnly): ?IResponse
    {
        return $participant->getAllResponses()->filterBy('authoritative', true)->first();
    }


    /**
     * Same principle as getAuthoritativeResponse as each Response will have an ai-generated
     * Feedback directly associated with it 1:1
     *
     * @param Participant $participant
     * @param $publishedOnly
     * @return IFeedback|null
     */
    public function getAuthoritativeFeedback(Participant $participant, $publishedOnly): ?IFeedback
    {
        $this->assertSameAssessment($participant);

        foreach ($participant->getAllFeedbacks() as $feedback) {
            /* Returns the feedback directly attributed to an authoritative response
             */
            /** @var \Schoolbox\Learning\Assessment\DialogAgent\Response\Model $response */
            $response = $feedback->getResponse();

            if ($response->getAuthoritative()) {
                return $feedback;
            }
        }

        /* No authoritative feedback exists yet
         */
        return null;
    }


    /**
     * Feedback for Dialog Agents is fully AI-generated.
     * Feedback is required if the authoritative response is submitted and does not yet have feedback
     *
     * @param Participant $participant
     * @return bool
     */
    public function isFeedbackRequired(Participant $participant): bool
    {
        $this->assertSameAssessment($participant);

        /* Check if a response exists; if there isn't one, we don't need a feedback for it
         */
        $response = $this->getAuthoritativeResponse($participant, true);

        if (!$response) {
            return false;
        }

        /* Check that it's been submitted too
         */
        if (!$response->isSubmitted()) {
            return false;
        }

        /* Authoritative response exists and is submitted; see if there's a feedback for it already.
         * If not, then we need it
         */
        $feedback = $this->getAuthoritativeFeedback($participant, true);

        return !$feedback;
    }


    /**
     * Decide if this participant has a published feedback. That's a bit of a weird hair-splitting given that
     * isFeedbackRequired() also exists, but ok
     * )
     * @param Participant $participant
     * @return bool
     */
    public function hasPublishedFeedback(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        /* Dialog Agents' feedback is automatically generated by the machine and is always considered
         * "immediately published" since it requires no human interaction to compose or release.
         *
         * TODO This is probably not great seeing as you implicitly accept whatever the AI said without any
         *  opportunity to intervene to correct or adjust whatever it generated.
         *
         *  Anyway, this logic is extremely similar to isFeedbackRequired, but there's a subtlety in its interpretation
         *  at the end; this runs the  same logic, but returns the inversion of isFeedbackRequired(), which is annoying
         */

        /* Check if a response exists; if there isn't one, we don't need a feedback for it
         */
        $response = $this->getAuthoritativeResponse($participant, true);

        if (!$response) {
            return false;
        }

        /* Check that it's been submitted too
         */
        if (!$response->isSubmitted()) {
            return false;
        }

        /* Authoritative response exists and is submitted; see if there's a feedback for it already.
         * If not, then we need it
         */
        $feedback = $this->getAuthoritativeFeedback($participant, true);

        return (bool)$feedback;
    }


    /**
     * Feedback for Dialog Agents is fully AI-generated.
     * There can't ever be a draft, it will always be the final version if it exists.
     *
     * @param Participant $participant
     * @return bool
     */
    public function hasPendingDraftAuthoritativeFeedback(Participant $participant): bool
    {
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


    /* Assertions
     * For some reason these throw exceptions when their requirements are not met, even though these aren't
     * error states, egregious misconfigurations or particularly extraordinary setups. I think these are
     * totally expected circumstances that we need to handle with a bit more delicacy.
     */

    /**
     * Check the dates set on this model are valid
     *
     * @throws InvalidArgumentException if the dates are not validly set
     * @return void
     */
    public function assertValidDates(): void
    {
        /* Check publish date against everything else
         * Don't check dates if in course
         */
        if ($this->getBaseContext() instanceof UnitSpecification) {
            return;
        }

        $this->assertDateSequence([
            'publish' => $this->getPublishAt(),
            'open' => $this->getOpenAt(),
            'due' => $this->getDueAt(),
            'late' => $this->getCloseAt()
        ]);
    }


    /**
     * Assert whether a student can create a response to this learning activity
     *
     * @throws \DomainException
     */
    public function assertCanRespond(?Participant $participant = null): void
    {
        /* This assessment must be an impl, not a spec
         */
        if ($this->getContext()->isSpecificationContext()) {
            throw new \DomainException('', ErrorMapper::ERROR_NO_COURSE_SPEC_INTERACTIONS);
        }

        /* You must be within the date window, between open and due/close
         */
        if ($participant != null) {
            $openDate = $participant->getOpenDate();
            $closeDate = $participant->getCloseDate();
        } else {
            $openDate = $this->getOpenAt();
            $closeDate = $this->getCloseAt();
        }

        if ($openDate->gt(\Alaress_Date_Format::now())) {
            throw new \DomainException('', ErrorMapper::ERROR_ASSESSMENT_NOT_YET_OPEN);
        }

        if (\Alaress_Date_Format::now()->gt($closeDate)) {
            throw new \DomainException('', ErrorMapper::ERROR_ASSESSMENT_CLOSED);
        }
    }

    /**
     * Used for Course Export.
     *
     * Copy implementation back to assessment along with any activity-specific fields beyond the ones
     * already pre-defined in AbstractAssessment's overwrite()
     */
    public function overwrite(IAssessable $source): IAssessable
    {
        parent::overwrite($source);

        /** @var \Schoolbox\Learning\Assessment\DialogAgent\Model $source */
        /* Specific to Dialog Agents activity
         */
        $this->setDuration($source->getDuration())
            ->setScenarioId($source->getScenarioId())
            /* The configuration of the dialog agent activity itself
             */
            ->setHelpfulness($source->getHelpfulness())
            ->setLanguage($source->getLanguage())
            ->setUseDifferentiationProfile($source->getUseDifferentiationProfile())
            ->setOpeningMessage($source->getOpeningMessage())
            ->setRules($source->getRules());

        return $this;
    }


    /**
     * @inheritDoc
     */
    public function isCustomParticipationCopyable(): bool
    {
        /* At the moment, we want to restrict the copying of custom participation
         * for dialog agents, which will be handled in the future, so for the time being, we will leave it out.
         */
        return false;
    }


    /* Getters/Setters
     */

    /**
     * @return int|null
     */
    public function getScenarioId(): ?int
    {
        return $this->scenarioId;
    }


    /**
     * @param int|null $scenarioId
     * @return $this
     */
    public function setScenarioId(?int $scenarioId): self
    {
        $this->scenarioId = $scenarioId;
        return $this;
    }


    /**
     * @return AssessmentModel|null
     */
    public function getAssessmentModel(): ?AssessmentModel
    {
        return $this->assessmentModel;
    }


    /**
     * @param AssessmentModel|null $assessmentModel
     * @return void
     */
    public function setAssessmentModel(?AssessmentModel $assessmentModel): void
    {
        $this->assessmentModel = $assessmentModel;
    }


    /**
     * @return User|null
     */
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }


    /**
     * @param User|null $createdBy
     * @return $this
     */
    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }


    /**
     * @return User|null
     */
    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }


    /**
     * @param User|null $updatedBy
     * @return $this
     */
    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }


    /**
     * @return User|null
     */
    public function getDeletedBy(): ?User
    {
        return $this->deletedBy;
    }


    /**
     * @param User|null $deletedBy
     * @return $this
     */
    public function setDeletedBy(?User $deletedBy): self
    {
        $this->deletedBy = $deletedBy;
        return $this;
    }


    /**
     * @return int|null
     */
    public function getDuration(): ?int
    {
        return $this->duration;
    }


    /**
     * @param int|null $duration
     * @return $this
     */
    public function setDuration(?int $duration): self
    {
        $this->duration = $duration;
        return $this;
    }


    /**
     * Does this activity have unlimited duration?
     *
     * @return bool
     */
    public function isDurationUnlimited(): bool
    {
        return $this->duration === self::DURATION_UNLIMITED;
    }


    /**
     * @return string|null
     */
    public function getLanguage(): ?string
    {
        return $this->language;
    }


    /**
     * @param string|null $language
     * @return $this
     */
    public function setLanguage(?string $language): self
    {
        $this->language = $language;
        return $this;
    }


    /**
     * @return Helpfulness|null
     */
    public function getHelpfulness(): ?Helpfulness
    {
        return $this->helpfulness;
    }


    /**
     * @param Helpfulness|null $helpfulness
     * @return $this
     */
    public function setHelpfulness(?Helpfulness $helpfulness): self
    {
        $this->helpfulness = $helpfulness;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getUseDifferentiationProfile(): ?bool
    {
        return $this->useDifferentiationProfile;
    }


    /**
     * @param bool|null $useDifferentiationProfile
     * @return $this
     */
    public function setUseDifferentiationProfile(?bool $useDifferentiationProfile): self
    {
        $this->useDifferentiationProfile = $useDifferentiationProfile;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getOpeningMessage(): ?string
    {
        return $this->openingMessage;
    }


    /**
     * @param string|null $openingMessage
     * @return $this
     */
    public function setOpeningMessage(?string $openingMessage): self
    {
        $this->openingMessage = $openingMessage;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getRules(): ?string
    {
        return $this->rules;
    }


    /**
     * @param string|null $rules
     * @return $this
     */
    public function setRules(?string $rules): self
    {
        $this->rules = $rules;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getPrompt(): ?string
    {
        return $this->prompt;
    }


    /**
     * @param string|null $prompt
     * @return $this
     */
    public function setPrompt(?string $prompt): self
    {
        $this->prompt = $prompt;
        return $this;
    }
}
