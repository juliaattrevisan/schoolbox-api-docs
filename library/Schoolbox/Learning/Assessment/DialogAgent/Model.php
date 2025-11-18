<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent;

use InvalidArgumentException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
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
    private const DEFAULT_TYPE_NAME = 'Dialog Agent';


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
     * @var string|null
     */
    private ?string $helpfulness = null;


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

        /* TODO Maybe something about activity duration should be in here...?
         * I think so... also whether you can create a new response depending on existing responses
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
        // TODO: Implement getAuthoritativeFeedback() method.
        //  foreach ($participant responses) {
        //      if $response->getAuthoritative() {
        //          return true;
        //      }
        //  }

        return null;
    }


    /**
     * TODO Not entirely sure what this means for Dialog Agents atm.
     *  I think we can get away with "no feedback required" (false) for now
     *
     * @param Participant $participant
     * @return bool
     */
    public function isFeedbackRequired(Participant $participant): bool
    {
        // TODO: Implement isFeedbackRequired() method.
        return false;
    }


    /**
     * TODO Not entirely sure what this means for Dialog Agents atm.
     *  I think we can get away with "no feedback required" (false) for now because the feedback
     *  that's generated will have no requirement for a human to help compose it.
     *
     * @param Participant $participant
     * @return bool
     */
    public function hasPendingDraftAuthoritativeFeedback(Participant $participant): bool
    {
        // TODO: Implement hasPendingDraftAuthoritativeFeedback() method.
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

        /* Specific to Dialog Agents activity
         */
        $this->setDuration($source->getDuration());
        $this->setScenarioId($source->getScenarioId());

        /* The configuration of the dialog agent activity itself
         */
        $this->setHelpfulness($source->getHelpfulness());
        $this->setLanguage($source->getLanguage());
        $this->setUseDifferentiationProfile($source->getUseDifferentiationProfile());
        $this->setOpeningMessage($source->getOpeningMessage());
        $this->setRules($source->getRules());

        return $this;
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
     * @return string|null
     */
    public function getHelpfulness(): ?string
    {
        return $this->helpfulness;
    }


    /**
     * @param string|null $helpfulness
     * @return $this
     */
    public function setHelpfulness(?string $helpfulness): self
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
